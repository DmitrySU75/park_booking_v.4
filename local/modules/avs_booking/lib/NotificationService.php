<?php

class AVSNotificationService
{
    private $adminEmail;
    private $managerEmail;
    private $b24Webhook;
    private $tgBotToken;

    public function __construct()
    {
        $this->adminEmail = \Bitrix\Main\Config\Option::get('avs_booking', 'admin_email', '');
        $this->managerEmail = \Bitrix\Main\Config\Option::get('avs_booking', 'manager_email', '');
        $this->b24Webhook = \Bitrix\Main\Config\Option::get('avs_booking', 'b24_webhook_url', '');
        $this->tgBotToken = \Bitrix\Main\Config\Option::get('avs_booking', 'tg_bot_token', '');
    }

    /**
     * Уведомление менеджера о новом бронировании
     */
    public function sendNewOrderNotification($order)
    {
        $message = "🆕 НОВОЕ БРОНИРОВАНИЕ\n\n";
        $message .= "Номер: {$order['ORDER_NUMBER']}\n";
        $message .= "Беседка: {$order['PAVILION_NAME']}\n";
        $message .= "Клиент: {$order['CLIENT_NAME']}\n";
        $message .= "Телефон: {$order['CLIENT_PHONE']}\n";
        $message .= "Начало: {$order['START_TIME']}\n";
        $message .= "Окончание: {$order['END_TIME']}\n";
        $message .= "Сумма: {$order['PRICE']} руб.\n";
        $message .= "Аванс: {$order['DEPOSIT_AMOUNT']} руб.\n";
        $message .= "Тип: {$order['RENTAL_TYPE']}\n";

        // Email менеджеру
        if ($this->managerEmail) {
            mail($this->managerEmail, 'Новое бронирование #' . $order['ORDER_NUMBER'], $message, 'Content-Type: text/plain; charset=utf-8');
        }

        // Email администратору
        if ($this->adminEmail) {
            mail($this->adminEmail, 'Новое бронирование #' . $order['ORDER_NUMBER'], $message, 'Content-Type: text/plain; charset=utf-8');
        }

        // Битрикс24
        $this->sendToBitrix24($order);

        // Telegram менеджеру
        $this->sendToTelegram($message);

        $this->logNotification($order['ID'], 'new_order', $this->managerEmail ?: $this->adminEmail);
    }

    /**
     * Отправка клиенту подтверждения бронирования
     */
    public function sendClientConfirmation($order)
    {
        $message = "✅ Ваше бронирование подтверждено!\n\n";
        $message .= "Номер: {$order['ORDER_NUMBER']}\n";
        $message .= "Беседка: {$order['PAVILION_NAME']}\n";
        $message .= "Дата: " . date('d.m.Y', strtotime($order['START_TIME'])) . "\n";
        $message .= "Время: " . date('H:i', strtotime($order['START_TIME'])) . " - " . date('H:i', strtotime($order['END_TIME'])) . "\n";
        $message .= "Сумма к оплате: {$order['PRICE']} руб.\n";
        $message .= "Аванс: {$order['DEPOSIT_AMOUNT']} руб.\n\n";
        $message .= "Ссылка для оплаты: https://" . $_SERVER['HTTP_HOST'] . "/payment/?order_id={$order['ID']}\n";

        if ($order['CLIENT_EMAIL']) {
            \CEvent::Send('AVS_BOOKING_NEW_ORDER', 's1', [
                'ORDER_NUMBER' => $order['ORDER_NUMBER'],
                'CLIENT_NAME' => $order['CLIENT_NAME'],
                'CLIENT_PHONE' => $order['CLIENT_PHONE'],
                'PAVILION_NAME' => $order['PAVILION_NAME'],
                'START_TIME' => $order['START_TIME'],
                'END_TIME' => $order['END_TIME'],
                'PRICE' => $order['PRICE']
            ], 'Y', '', [$order['CLIENT_EMAIL']]);
        }

        if ($order['CLIENT_TG_ID']) {
            $this->sendTelegramMessage($order['CLIENT_TG_ID'], $message);
        }

        $this->logNotification($order['ID'], 'client_confirmation', $order['CLIENT_EMAIL'] ?: $order['CLIENT_TG_ID']);
    }

    /**
     * Отправка уведомления об успешной оплате
     */
    public function sendPaymentSuccessNotification($order)
    {
        $message = "💳 Оплата получена!\n\n";
        $message .= "Номер бронирования: {$order['ORDER_NUMBER']}\n";
        $message .= "Беседка: {$order['PAVILION_NAME']}\n";
        $message .= "Сумма: {$order['PAID_AMOUNT']} руб.\n\n";
        $message .= "Спасибо за бронирование! Ждем вас!\n";

        if ($order['CLIENT_EMAIL']) {
            \CEvent::Send('AVS_BOOKING_PAYMENT_SUCCESS', 's1', [
                'ORDER_NUMBER' => $order['ORDER_NUMBER'],
                'CLIENT_NAME' => $order['CLIENT_NAME'],
                'AMOUNT' => $order['PAID_AMOUNT']
            ], 'Y', '', [$order['CLIENT_EMAIL']]);
        }

        if ($order['CLIENT_TG_ID']) {
            $this->sendTelegramMessage($order['CLIENT_TG_ID'], $message);
        }

        $this->logNotification($order['ID'], 'payment_success', $order['CLIENT_EMAIL'] ?: $order['CLIENT_TG_ID']);
    }

    /**
     * Отправка напоминания о бронировании
     */
    public function sendReminder($order)
    {
        $startTime = new \DateTime($order['START_TIME']);
        $now = new \DateTime();
        $hoursLeft = ($startTime->getTimestamp() - $now->getTimestamp()) / 3600;

        if ($hoursLeft <= 24 && $hoursLeft > 0) {
            $message = "🔔 Напоминание о бронировании!\n\n";
            $message .= "Завтра в " . $startTime->format('H:i') . " ваша беседка \"{$order['PAVILION_NAME']}\"\n";
            $message .= "Номер бронирования: {$order['ORDER_NUMBER']}\n";

            if ($order['CLIENT_TG_ID']) {
                $this->sendTelegramMessage($order['CLIENT_TG_ID'], $message);
            }

            $this->logNotification($order['ID'], 'reminder', $order['CLIENT_TG_ID']);
        }
    }

    /**
     * Отправка в Битрикс24
     */
    private function sendToBitrix24($order)
    {
        if (!$this->b24Webhook) return;

        $leadData = [
            'TITLE' => 'Бронирование беседки ' . $order['PAVILION_NAME'],
            'NAME' => $order['CLIENT_NAME'],
            'PHONE' => [['VALUE' => $order['CLIENT_PHONE'], 'VALUE_TYPE' => 'WORK']],
            'COMMENTS' => "Бронирование #{$order['ORDER_NUMBER']}\nНачало: {$order['START_TIME']}\nОкончание: {$order['END_TIME']}\nСумма: {$order['PRICE']} руб.",
            'SOURCE_ID' => 'WEB',
            'UF_CRM_ORDER_NUMBER' => $order['ORDER_NUMBER']
        ];

        $ch = curl_init($this->b24Webhook . '/crm.lead.add.json');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['fields' => $leadData]));
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Отправка в Telegram
     */
    private function sendToTelegram($message)
    {
        $managerChatId = \Bitrix\Main\Config\Option::get('avs_booking', 'tg_manager_chat_id', '');
        if ($this->tgBotToken && $managerChatId) {
            $this->sendTelegramMessage($managerChatId, $message);
        }
    }

    /**
     * Отправка Telegram сообщения
     */
    private function sendTelegramMessage($chatId, $message)
    {
        if (!$this->tgBotToken) return;

        $url = "https://api.telegram.org/bot{$this->tgBotToken}/sendMessage";
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Логирование уведомления
     */
    private function logNotification($orderId, $type, $recipient)
    {
        global $DB;
        $DB->Insert('avs_booking_notifications', [
            'ORDER_ID' => intval($orderId),
            'TYPE' => "'" . $DB->ForSql($type) . "'",
            'RECIPIENT' => "'" . $DB->ForSql($recipient) . "'",
            'CHANNEL' => "'email'",
            'STATUS' => "'sent'",
            'CREATED_AT' => "'" . date('Y-m-d H:i:s') . "'"
        ]);
    }
}
