<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once __DIR__ . '/include.php';

$logFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/payment_debug.log';

function writeLog($message)
{
    global $logFile;
    file_put_contents($logFile, date('[Y-m-d H:i:s]') . ' [webhook] ' . $message . PHP_EOL, FILE_APPEND);
}

writeLog('========== ВЕБХУК ВЫЗВАН ==========');

$source = file_get_contents('php://input');
writeLog('Тело запроса: ' . $source);

$data = json_decode($source, true);

if (!isset($data['event']) || $data['event'] !== 'payment.succeeded') {
    writeLog('Не тот тип события или событие не оплата');
    http_response_code(200);
    echo 'OK';
    exit;
}

$paymentId = $data['object']['id'];
writeLog("Платёж оплачен: $paymentId");

session_start();

if (isset($_SESSION['yookassa_payment']) && $_SESSION['yookassa_payment']['payment_id'] === $paymentId) {
    $bookingData = $_SESSION['yookassa_payment']['booking_data'];
    writeLog("Данные бронирования найдены: " . print_r($bookingData, true));

    $result = AVSBookingModule::createBooking(
        $bookingData['resource_id'],
        $bookingData['start_time'],
        $bookingData['end_time'],
        $bookingData['user_data']
    );

    if (isset($result['referenceNumber'])) {
        writeLog("Бронирование создано: {$result['referenceNumber']}");

        AVSBookingModule::sendNotifications(
            $result['referenceNumber'],
            $bookingData,
            $bookingData['deposit_amount']
        );

        unset($_SESSION['yookassa_payment']);
        writeLog("Сессия очищена");
    } else {
        writeLog("ОШИБКА: Бронирование не создано");
    }
} else {
    writeLog("Данные бронирования не найдены в сессии");
}

http_response_code(200);
echo 'OK';
