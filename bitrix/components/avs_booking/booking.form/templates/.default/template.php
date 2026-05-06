<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$gazebo = $arResult['GAZEBO'];
$rentalTypes = $arResult['RENTAL_TYPES'];
$availableSlots = $arResult['AVAILABLE_SLOTS'];
$selectedDate = $arResult['SELECTED_DATE'];
$workEndHour = $arResult['WORK_END_HOUR'];
$minHours = $arResult['MIN_HOURS'];
$errors = $arResult['ERRORS'] ?? [];
$post = $arResult['POST'] ?? [];

CJSCore::Init(['jquery']);
?>

<div class="avs-booking-form">
    <h2>Бронирование беседки: <?= htmlspecialcharsbx($gazebo['name']) ?></h2>

    <div class="work-hours-info">
        ⏰ Время работы: 10:00 - <?= $workEndHour ?>:00
    </div>

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
            <label for="hours">Продолжительность (часов) *</label>
            <select name="hours" id="hours">
                <option value="">Выберите продолжительность</option>
                <?php for ($i = $minHours; $i <= 12; $i++): ?>
                    <option value="<?= $i ?>" <?= ($post['hours'] == $i) ? 'selected' : '' ?>><?= $i ?> час(ов)</option>
                <?php endfor; ?>
            </select>
            <small>Минимальная продолжительность: <?= $minHours ?> часа</small>
        </div>

        <div class="form-group">
            <label for="client_name">Ваше имя *</label>
            <input type="text" name="client_name" id="client_name" value="<?= htmlspecialcharsbx($post['client_name'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label for="client_phone">Телефон *</label>
            <input type="tel" name="client_phone" id="client_phone" class="phone-mask" value="<?= htmlspecialcharsbx($post['client_phone'] ?? '') ?>" placeholder="+7(999)999-99-99" required>
        </div>

        <div class="form-group">
            <label for="client_email">Email (необязательно)</label>
            <input type="email" name="client_email" id="client_email" value="<?= htmlspecialcharsbx($post['client_email'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="discount_code">Промокод</label>
            <input type="text" name="discount_code" id="discount_code" value="<?= htmlspecialcharsbx($post['discount_code'] ?? '') ?>" placeholder="Введите промокод">
            <button type="button" id="apply_discount" class="btn-small">Применить</button>
        </div>

        <div class="form-group">
            <label for="comment">Комментарий</label>
            <textarea name="comment" id="comment" rows="3"><?= htmlspecialcharsbx($post['comment'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <button type="submit" class="btn-submit">Забронировать</button>
        </div>

        <div id="price-preview" class="price-preview" style="display: none;">
            <div>Сумма: <span class="price-value">0</span> руб.</div>
            <div class="deposit-info">Аванс: <span class="deposit-value">0</span> руб.</div>
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

    .work-hours-info {
        background: #e3f2fd;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 20px;
        text-align: center;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-sizing: border-box;
    }

    .btn-submit {
        background: #4CAF50;
        color: white;
        padding: 12px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        width: 100%;
        font-size: 16px;
    }

    .btn-small {
        background: #2196f3;
        color: white;
        padding: 6px 12px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        margin-top: 5px;
    }

    .avs-booking-errors {
        margin-bottom: 20px;
        padding: 10px;
        background: #ffebee;
        border-radius: 4px;
    }

    .error-message {
        color: #c62828;
    }

    .price-preview {
        margin-top: 20px;
        padding: 12px;
        background: #e8f5e9;
        border-radius: 4px;
        text-align: center;
    }

    .price-value {
        font-size: 24px;
        font-weight: bold;
        color: #2e7d32;
    }

    .deposit-info {
        margin-top: 5px;
        color: #666;
    }
</style>

<script>
    var gazeboName = '<?= $gazebo['name'] ?>';
    var minHours = <?= $minHours ?>;

    $(document).ready(function() {
        $('#rental_type').change(function() {
            var rentalType = $(this).val();
            if (rentalType === 'hourly') {
                $('.hourly-fields').show();
            } else {
                $('.hourly-fields').hide();
                if (rentalType !== '') {
                    calculatePrice();
                }
            }
        });

        $('#date, #start_hour, #hours, #discount_code').change(function() {
            calculatePrice();
        });

        $('#apply_discount').click(function() {
            calculatePrice();
        });

        function calculatePrice() {
            var rentalType = $('#rental_type').val();
            var date = $('#date').val();
            var hours = $('#hours').val();
            var discountCode = $('#discount_code').val();

            if (!rentalType || !date) return;

            if (rentalType === 'hourly' && !hours) return;

            $.ajax({
                url: '/local/modules/avs_booking/ajax.php',
                method: 'POST',
                data: {
                    action: 'get_price',
                    pavilion_name: gazeboName,
                    rental_type: rentalType,
                    date: date,
                    hours: hours,
                    discount_code: discountCode
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('.price-value').text(response.total_price.toLocaleString('ru-RU'));
                        $('.deposit-value').text(response.deposit_amount.toLocaleString('ru-RU'));
                        $('#price-preview').show();
                    }
                }
            });
        }

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
        }
    });
</script>