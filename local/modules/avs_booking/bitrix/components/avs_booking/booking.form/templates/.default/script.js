/**
 * Файл: /local/modules/avs_booking/bitrix/components/avs_booking/booking.form/templates/.default/script.js
 */

$(document).ready(function () {
    $('#rental_type').change(function () {
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

    $('#date, #start_hour, #hours, #discount_code').change(function () {
        calculatePrice();
    });

    $('#apply_discount').click(function () {
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
                pavilion_id: gazeboId,
                rental_type: rentalType,
                date: date,
                hours: hours,
                discount_code: discountCode
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $('.price-value').text(response.total_price.toLocaleString('ru-RU'));
                    $('.deposit-value').text(response.deposit_amount.toLocaleString('ru-RU'));
                    if (response.discount_amount > 0) {
                        $('.discount-value').text(response.discount_amount.toLocaleString('ru-RU'));
                        $('.discount-info').show();
                    } else {
                        $('.discount-info').hide();
                    }
                    $('#price-preview').show();
                } else if (response.error) {
                    $('#price-preview').hide();
                    alert(response.error);
                }
            },
            error: function () {
                $('#price-preview').hide();
            }
        });
    }

    $('#date').change(function () {
        var selectedDate = $(this).val();

        $.ajax({
            url: '/local/modules/avs_booking/ajax.php',
            method: 'POST',
            data: {
                action: 'get_date_restrictions',
                pavilion_id: gazeboId,
                date: selectedDate
            },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data.is_special) {
                    var allowedTypes = response.data.allowed_types;
                    $('#rental_type option').each(function () {
                        var val = $(this).val();
                        if (val && allowedTypes.indexOf(val) === -1) {
                            $(this).hide();
                        } else {
                            $(this).show();
                        }
                    });
                    if (response.data.description) {
                        $('.restriction-note').remove();
                        $('.work-hours-info').after('<div class="restriction-note">⚠️ ' + response.data.description + '</div>');
                    }
                } else {
                    $('#rental_type option').show();
                    $('.restriction-note').remove();
                }
            }
        });
    });

    var phoneInput = document.getElementById('client_phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function (e) {
            var x = e.target.value.replace(/\D/g, '').match(/(\d{0,1})(\d{0,3})(\d{0,3})(\d{0,2})(\d{0,2})/);
            if (x) {
                e.target.value = '+7' + (x[2] ? '(' + x[2] + ')' : '') +
                    (x[3] ? x[3] : '') + (x[4] ? '-' + x[4] : '') +
                    (x[5] ? '-' + x[5] : '');
            }
        });

        phoneInput.addEventListener('focus', function (e) {
            if (e.target.value === '') {
                e.target.value = '+7(';
            }
        });
    }
});