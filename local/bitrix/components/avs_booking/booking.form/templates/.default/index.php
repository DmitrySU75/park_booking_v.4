<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

$gazebo = $arResult['GAZEBO'];
$rentalTypes = $arResult['RENTAL_TYPES'];
$availableSlots = $arResult['AVAILABLE_SLOTS'];
$selectedDate = $arResult['SELECTED_DATE'];
$errors = $arResult['ERRORS'] ?? [];
$post = $arResult['POST'] ?? [];

CJSCore::Init(['jquery']);
?>

<div class="avs-booking-form">
    <h2>Бронирование беседки: <?= htmlspecialcharsbx($gazebo['name']) ?></h2>

    <?php if (!empty($errors)): ?>
        <div class="avs-booking-errors">
            <?php foreach ($errors as $error): ?>
                <div class="error-message"><?= htmlspecialcharsbx($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="" id="booking-form">
        <?= bitrix_sessid_post() ?>

        <div class="form-group">
            <label for="date">Дата бронирования *</label>
            <input type="date" name="date" id="date" value="<?= htmlspecialcharsbx($post['date'] ?: $selectedDate) ?>" min="<?= date('Y-m-d') ?>" required>
        </div>

        <div class="form-group">
            <label for="rental_type">Тип аренды *</label>
            <select name="rental_type" id="rental_type" required>
                <option value="">Выберите тип аренды</option>
                <?php foreach ($rentalTypes as $code => $type): ?>
                    <option value="<?= $code ?>" data-price="<?= $type['price'] ?>" <?= ($post['rental_type'] == $code) ? 'selected' : '' ?>>
                        <?= htmlspecialcharsbx($type['label']) ?> - <?= number_format($type['price'], 0, '.', ' ') ?> руб.
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group hourly-fields" style="display: none;">
            <label for="start_hour">Время начала</label>
            <select name="start_hour" id="start_hour">
                <option value="">Выберите время</option>
                <?php foreach ($availableSlots as $slot): ?>
                    <option value="<?= $slot['hour'] ?>" <?= ($post['start_hour'] == $slot['hour']) ? 'selected' : '' ?>>
                        <?= $slot['label'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group hourly-fields" style="display: none;">
            <label for="hours">Продолжительность (часов)</label>
            <select name="hours" id="hours">
                <option value="">Выберите продолжительность</option>
                <?php for ($i = $gazebo['min_hours']; $i <= 12; $i++): ?>
                    <option value="<?= $i ?>" <?= ($post['hours'] == $i) ? 'selected' : '' ?>><?= $i ?> час(ов)</option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="client_name">Ваше имя *</label>
            <input type="text" name="client_name" id="client_name" value="<?= htmlspecialcharsbx($post['client_name'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label for="client_phone">Телефон *</label>
            <input type="tel" name="client_phone" id="client_phone" class="phone-mask" value="<?= htmlspecialcharsbx($post['client_phone'] ?? '') ?>" placeholder="+7(___) ___-__-__" required>
        </div>

        <div class="form-group">
            <label for="client_email">Email (необязательно)</label>
            <input type="email" name="client_email" id="client_email" value="<?= htmlspecialcharsbx($post['client_email'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="comment">Комментарий</label>
            <textarea name="comment" id="comment" rows="3"><?= htmlspecialcharsbx($post['comment'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <button type="submit" class="btn-submit">Забронировать</button>
        </div>

        <div id="price-preview" class="price-preview" style="display: none;">
            Итого: <span class="price-value">0</span> руб.
        </div>
    </form>
</div>

<style>
    .avs-booking-form {
        max-width: 600px;
        margin: 0 auto;
        padding: 20px;
        background: #f9f9f9;
        border-radius: 8px;
    }

    .avs-booking-form .form-group {
        margin-bottom: 15px;
    }

    .avs-booking-form label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }

    .avs-booking-form input,
    .avs-booking-form select,
    .avs-booking-form textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        box-sizing: border-box;
    }

    .avs-booking-form .btn-submit {
        background: #4CAF50;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        width: 100%;
    }

    .avs-booking-form .btn-submit:hover {
        background: #45a049;
    }

    .avs-booking-errors {
        margin-bottom: 20px;
        padding: 10px;
        background: #ffebee;
        border-radius: 4px;
    }

    .avs-booking-errors .error-message {
        color: #c62828;
        margin-bottom: 5px;
    }

    .price-preview {
        margin-top: 15px;
        padding: 10px;
        background: #e3f2fd;
        border-radius: 4px;
        text-align: center;
        font-size: 18px;
    }

    .price-preview .price-value {
        font-weight: bold;
        color: #1976d2;
    }
</style>

<script>
    $(document).ready(function() {
        $('#rental_type').change(function() {
            var rentalType = $(this).val();

            if (rentalType === 'hourly') {
                $('.hourly-fields').show();
                checkAvailability();
            } else {
                $('.hourly-fields').hide();
                if (rentalType !== '') {
                    calculatePrice();
                }
            }
        });

        $('#date').change(function() {
            location.href = '?date=' + $(this).val();
        });

        $('#start_hour, #hours').change(function() {
            checkAvailability();
        });

        function checkAvailability() {
            var rentalType = $('#rental_type').val();
            var date = $('#date').val();
            var startHour = $('#start_hour').val();
            var hours = $('#hours').val();

            if (!rentalType || !date) return;

            if (rentalType === 'hourly' && (!startHour || !hours)) return;

            $.ajax({
                url: '/local/modules/avs_booking/ajax.php',
                method: 'POST',
                data: {
                    action: 'check_availability',
                    resource_id: <?= $gazebo['resource_id'] ?>,
                    date: date,
                    rental_type: rentalType,
                    start_hour: startHour,
                    hours: hours,
                    element_id: <?= $gazebo['id'] ?>
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        if (response.available) {
                            calculatePrice();
                            $('#price-preview').show();
                        } else {
                            $('#price-preview').hide();
                            alert('Выбранное время недоступно');
                        }
                    }
                }
            });
        }

        function calculatePrice() {
            var rentalType = $('#rental_type').val();
            var date = $('#date').val();
            var hours = $('#hours').val();
            var selectedOption = $('#rental_type option:selected');
            var basePrice = parseFloat(selectedOption.data('price') || 0);

            var finalPrice = basePrice;
            if (rentalType === 'hourly' && hours) {
                finalPrice = basePrice * parseInt(hours);
            }

            $('.price-value').text(finalPrice.toLocaleString('ru-RU') + ' руб.');
        }

        // Маска телефона
        var phoneInput = document.getElementById('client_phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                var x = e.target.value.replace(/\D/g, '').match(/(\d{0,1})(\d{0,3})(\d{0,3})(\d{0,2})(\d{0,2})/);
                if (x) {
                    e.target.value = '+7' + (x[2] ? '(' + x[2] + ')' : '') +
                        (x[3] ? x[3] : '') + (x[4] ? '-' + x[4] : '') +
                        (x[5] ? '-' + x[5] : '');
                }
            });

            phoneInput.addEventListener('focus', function(e) {
                if (e.target.value === '') {
                    e.target.value = '+7(';
                }
            });
        }
    });
</script>