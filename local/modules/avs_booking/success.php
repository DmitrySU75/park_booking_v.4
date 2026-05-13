<<<<<<< HEAD
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
=======
<?php
/**
 * Файл: /local/modules/avs_booking/success.php
 * Страница успешного бронирования
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

$orderId = isset($_REQUEST['order_id']) ? (int)$_REQUEST['order_id'] : 0;
$orderNumber = '';

if ($orderId > 0 && \Bitrix\Main\Loader::includeModule('avs_booking')) {
    $order = \AVS\Booking\Order::get($orderId);
    if ($order) {
        $orderNumber = htmlspecialchars($order['ORDER_NUMBER'], ENT_QUOTES, 'UTF-8');
    }
}

$successMessage = '';
if (isset($_SESSION['booking_success_message'])) {
    $successMessage = htmlspecialchars($_SESSION['booking_success_message'], ENT_QUOTES, 'UTF-8');
    unset($_SESSION['booking_success_message']);
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Бронирование создано</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
        }
        .success {
            color: green;
            font-size: 24px;
            margin-bottom: 20px;
        }
        .message {
            font-size: 18px;
            margin-bottom: 30px;
        }
        .button {
            background: #2e7d32;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>

<body>
    <div class="success">✅ Бронирование успешно создано!</div>
    <div class="message">
        <?php if ($orderNumber): ?>
            Номер вашего бронирования: <strong><?= $orderNumber ?></strong><br>
        <?php endif; ?>
        <?php if ($successMessage): ?>
            <?= $successMessage ?><br>
        <?php endif; ?>
        Подтверждение отправлено на вашу электронную почту.
    </div>
    <a href="/" class="button">Вернуться на главную</a>
</body>

</html>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog.php'; ?>
>>>>>>> efab6c89e78954f385bd7a0806927d1ecb5fc2bc
