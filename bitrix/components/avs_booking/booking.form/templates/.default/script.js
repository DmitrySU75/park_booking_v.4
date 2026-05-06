$(document).ready(function () {
    $('#rental_type').change(function () {
        var rentalType = $(this).val();

        if (rentalType === 'hourly') {
            $('.hourly-fields').show();
            checkAvailability();
        } else {
            $('.hourly-fields').hide();
            if (rentalType !== '') {
                calculatePrice();
                $('#price-preview').show();
            }
        }
    });

    $('#date').change(function () {
        location.href = '?date=' + $(this).val();
    });

    $('#start_hour, #hours').change(function () {
        checkAvailability();
    });

    function checkAvailability() {
        var rentalType = $('#rental_type').val();
        var date = $('#date').val();
        var startHour = $('#start_hour').val();
        var hours = $('#hours').val();

        if (!rentalType || !date) return;

        if (rentalType === 'hourly' && (!startHour || !hours)) return;

        var requestData = {
            action: 'check_availability',
            resource_id: gazeboResourceId,
            date: date,
            rental_type: rentalType,
            element_id: gazeboElementId
        };

        if (rentalType === 'hourly') {
            requestData.start_hour = startHour;
            requestData.hours = hours;
        }

        $.ajax({
            url: '/local/modules/avs_booking/ajax.php',
            method: 'POST',
            data: requestData,
            dataType: 'json',
            success: function (response) {
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