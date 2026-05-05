<?php

class AVSNotificationService
{
    private $adminEmail;
    private $b24Webhook;

    public function __construct()
    {
        $this->adminEmail = \Bitrix\Main\Config\Option::get('avs_booking', 'admin_email', '');
        $this->b24Webhook = \Bitrix\Main\Config\Option::get('avs_booking', 'b24_webhook_url', '');
    }

    public function sendAdminEmail($reference, $bookingData, $depositAmount)
    {
        if (!$this->adminEmail) {
            return false;
        }

        $message = "Новое бронирование!\n\n";
        $message .= "Номер: " . $reference . "\n";
        $message .= "Беседка: " . ($bookingData['pavilion_name'] ?? '') . "\n";
        $message .= "Клиент: " . ($bookingData['client_name'] ?? '') . "\n";
        $message .= "Телефон: " . ($bookingData['client_phone'] ?? '') . "\n";
        $message .= "Начало: " . ($bookingData['start_time'] ?? '') . "\n";
        $message .= "Окончание: " . ($bookingData['end_time'] ?? '') . "\n";
        $message .= "Сумма: " . $depositAmount . " руб.\n";

        return mail($this->adminEmail, 'Новое бронирование #' . $reference, $message, 'Content-Type: text/plain; charset=utf-8');
    }

    public function sendBitrix24Lead($reference, $bookingData, $depositAmount)
    {
        if (!$this->b24Webhook) {
            return false;
        }

        $leadData = [
            'TITLE' => 'Бронирование беседки ' . ($bookingData['pavilion_name'] ?? ''),
            'NAME' => $bookingData['client_name'] ?? '',
            'PHONE' => [['VALUE' => $bookingData['client_phone'] ?? '', 'VALUE_TYPE' => 'WORK']],
            'COMMENTS' => "Бронирование #{$reference}\nНачало: {$bookingData['start_time']}\nОкончание: {$bookingData['end_time']}\nСумма: {$depositAmount} руб.",
            'SOURCE_ID' => 'WEB'
        ];

        $ch = curl_init($this->b24Webhook . '/crm.lead.add.json');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['fields' => $leadData]));

        $response = curl_exec($ch);
        curl_close($ch);

        return $response !== false;
    }

    public function sendClientPaymentNotification($order)
    {
        if (!$order['CLIENT_EMAIL']) {
            return false;
        }

        $eventFields = [
            'ORDER_NUMBER' => $order['ORDER_NUMBER'],
            'CLIENT_NAME' => $order['CLIENT_NAME'],
            'PAVILION_NAME' => $order['PAVILION_NAME'],
            'AMOUNT' => $order['PAID_AMOUNT'],
            'START_TIME' => $order['START_TIME']->toString(),
            'END_TIME' => $order['END_TIME']->toString()
        ];

        return \CEvent::Send('AVS_BOOKING_PAYMENT_SUCCESS', 's1', $eventFields, 'Y', '', [$order['CLIENT_EMAIL']]);
    }

    public function sendTimeExtensionNotification($order)
    {
        if (!$this->adminEmail) {
            return false;
        }

        $eventFields = [
            'ORDER_NUMBER' => $order['ORDER_NUMBER'],
            'CLIENT_NAME' => $order['CLIENT_NAME'],
            'PAVILION_NAME' => $order['PAVILION_NAME'],
            'OLD_END_TIME' => $order['END_TIME']->toString(),
            'NEW_END_TIME' => $order['EXTENDED_END_TIME']->toString(),
            'ADDITIONAL_PRICE' => $order['PRICE'] - $order['PAID_AMOUNT']
        ];

        return \CEvent::Send('AVS_BOOKING_TIME_EXTENDED', 's1', $eventFields, 'Y', '', [$this->adminEmail]);
    }
}
