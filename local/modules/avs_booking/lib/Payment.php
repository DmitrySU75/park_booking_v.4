<?php

namespace AVS\Booking;

class Payment
{
    public static function createPayment($orderId, $returnUrl)
    {
        $order = Order::get($orderId);

        if (!$order) {
            return ['success' => false, 'error' => 'Order not found'];
        }

        $legalEntity = $order['LEGAL_ENTITY'];

        $legalSettings = self::getLegalEntitySettings($legalEntity);

        if (!$legalSettings['shop_id'] || !$legalSettings['secret_key']) {
            return ['success' => false, 'error' => 'Payment settings not configured for this legal entity'];
        }

        $paymentData = [
            'amount' => [
                'value' => $order['PRICE'],
                'currency' => 'RUB'
            ],
            'payment_method_data' => [
                'type' => 'bank_card'
            ],
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $returnUrl
            ],
            'description' => 'Бронирование беседки ' . $order['PAVILION_NAME'] . ' (' . $order['ORDER_NUMBER'] . ')',
            'metadata' => [
                'order_id' => $orderId,
                'order_number' => $order['ORDER_NUMBER'],
                'legal_entity' => $legalEntity
            ]
        ];

        $yookassa = new YookassaHandler($legalSettings['shop_id'], $legalSettings['secret_key']);
        $result = $yookassa->createPayment($paymentData);

        if ($result && isset($result['id'])) {
            Order::updatePaymentInfo($orderId, $result['id'], 'pending', 0);
            return [
                'success' => true,
                'payment_id' => $result['id'],
                'confirmation_url' => $result['confirmation']['confirmation_url']
            ];
        }

        return ['success' => false, 'error' => 'Failed to create payment'];
    }

    public static function handleWebhook()
    {
        $source = file_get_contents('php://input');
        $data = json_decode($source, true);

        if (!isset($data['object']['id'])) {
            return;
        }

        $paymentId = $data['object']['id'];
        $paymentStatus = $data['object']['status'];

        $orders = Order::getList(['PAYMENT_ID' => $paymentId], 1, 0);

        if (empty($orders)) {
            return;
        }

        $order = $orders[0];
        $legalEntity = $order['LEGAL_ENTITY'];
        $legalSettings = self::getLegalEntitySettings($legalEntity);

        $yookassa = new YookassaHandler($legalSettings['shop_id'], $legalSettings['secret_key']);
        $paymentInfo = $yookassa->getPaymentInfo($paymentId);

        if ($paymentInfo && $paymentInfo['status'] == 'succeeded') {
            $paidAmount = $paymentInfo['amount']['value'];
            Order::updatePaymentInfo($order['ID'], $paymentId, 'succeeded', $paidAmount);
            Order::updateStatus($order['ID'], 'paid');

            self::sendPaymentSuccessNotification($order);
        } elseif ($paymentInfo && $paymentInfo['status'] == 'canceled') {
            Order::updatePaymentInfo($order['ID'], $paymentId, 'canceled', 0);
            Order::updateStatus($order['ID'], 'payment_failed');
        }
    }

    private static function getLegalEntitySettings($legalEntity)
    {
        $settings = [
            AVS_LEGAL_BETON_SYSTEMS => [
                'shop_id' => \Bitrix\Main\Config\Option::get('avs_booking', 'beton_systems_shop_id', ''),
                'secret_key' => \Bitrix\Main\Config\Option::get('avs_booking', 'beton_systems_secret_key', ''),
                'name' => 'ООО "Бетонные Системы"'
            ],
            AVS_LEGAL_PARK_VICTORY => [
                'shop_id' => \Bitrix\Main\Config\Option::get('avs_booking', 'park_victory_shop_id', ''),
                'secret_key' => \Bitrix\Main\Config\Option::get('avs_booking', 'park_victory_secret_key', ''),
                'name' => 'СК "Парк победы" ООО'
            ]
        ];

        return $settings[$legalEntity] ?? $settings[AVS_LEGAL_BETON_SYSTEMS];
    }

    private static function sendPaymentSuccessNotification($order)
    {
        $adminEmail = \Bitrix\Main\Config\Option::get('avs_booking', 'admin_email', '');

        if ($adminEmail) {
            $eventFields = [
                'ORDER_NUMBER' => $order['ORDER_NUMBER'],
                'CLIENT_NAME' => $order['CLIENT_NAME'],
                'PAVILION_NAME' => $order['PAVILION_NAME'],
                'AMOUNT' => $order['PAID_AMOUNT'],
                'START_TIME' => $order['START_TIME']->toString(),
                'END_TIME' => $order['END_TIME']->toString()
            ];

            \CEvent::Send('AVS_BOOKING_PAYMENT_SUCCESS', 's1', $eventFields, 'Y', '', [$adminEmail]);
        }

        if ($order['CLIENT_EMAIL']) {
            \CEvent::Send('AVS_BOOKING_PAYMENT_SUCCESS', 's1', $eventFields, 'Y', '', [$order['CLIENT_EMAIL']]);
        }
    }
}
