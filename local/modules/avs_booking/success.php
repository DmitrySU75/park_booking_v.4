<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Бронирование создано");
?>

<?php
if (\Bitrix\Main\Loader::includeModule('avs_booking')) {
    $orderId = (int)$_REQUEST['order_id'];
    if ($orderId) {
        $order = \AVS\Booking\Order::get($orderId);
        if ($order) {
            ?>
            <div class="booking-success">
                <h2>✅ Бронирование успешно создано!</h2>
                <p>Номер вашего бронирования: <strong><?= htmlspecialcharsbx($order['ORDER_NUMBER']) ?></strong></p>
                <p>Подтверждение отправлено на вашу электронную почту.</p>
                <p>Сумма к оплате: <strong><?= number_format($order['DEPOSIT_AMOUNT'], 0, '.', ' ') ?> руб.</strong></p>
                
                <?php if ($order['DEPOSIT_AMOUNT'] > 0 && $order['PAID_AMOUNT'] < $order['DEPOSIT_AMOUNT']): ?>
                    <button id="pay-button" data-order-id="<?= $orderId ?>" class="btn-pay">Оплатить сейчас</button>
                    <div id="payment-form" style="display:none;"></div>
                <?php endif; ?>
            </div>
            
            <script src="https://yookassa.ru/checkout-widget/v1/checkout-widget.js"></script>
            <script>
                document.getElementById('pay-button')?.addEventListener('click', function() {
                    fetch('/local/modules/avs_booking/ajax.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=create_payment&order_id=' + this.dataset.orderId + '&return_url=<?= urlencode("https://" . $_SERVER["HTTP_HOST"] . "/booking-success/") ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.confirmation_url) {
                            const checkout = new YooKassaCheckoutWidget({
                                confirmation_token: data.confirmation_token,
                                return_url: '<?= "https://" . $_SERVER["HTTP_HOST"] . "/booking-success/" ?>'
                            });
                            checkout.render('payment-form');
                            document.getElementById('payment-form').style.display = 'block';
                        } else if (data.error) {
                            alert('Ошибка: ' + data.error);
                        }
                    });
                });
            </script>
            <?php
        }
    }
}
?>

<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php"); ?>