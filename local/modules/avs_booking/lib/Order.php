<?php

namespace AVS\Booking;

use Bitrix\Main\Type\DateTime;

class Order
{
    public static function create($data)
    {
        $legalEntity = \AVSBookingModule::getLegalEntityByPavilion($data['pavilion_name']);
        $orderNumber = self::generateOrderNumber();

        // Расчет цены с учетом тарифов и скидок
        $priceData = TariffManager::calculatePrice(
            $data['pavilion_name'],
            $data['rental_type'],
            substr($data['start_time'], 0, 10),
            $data['duration_hours'] ?? null,
            $data['discount_code'] ?? null
        );

        if (isset($priceData['error'])) {
            return false;
        }

        $result = OrderTable::add([
            'ORDER_NUMBER' => $orderNumber,
            'PAVILION_ID' => $data['pavilion_id'],
            'PAVILION_NAME' => $data['pavilion_name'],
            'LEGAL_ENTITY' => $legalEntity,
            'CLIENT_NAME' => $data['client_name'],
            'CLIENT_PHONE' => $data['client_phone'],
            'CLIENT_EMAIL' => $data['client_email'] ?? '',
            'CLIENT_TG_ID' => $data['client_tg_id'] ?? '',
            'START_TIME' => new DateTime($data['start_time']),
            'END_TIME' => new DateTime($data['end_time']),
            'PRICE' => $priceData['total_price'],
            'DEPOSIT_AMOUNT' => $priceData['deposit_amount'],
            'DISCOUNT_AMOUNT' => $priceData['discount_amount'],
            'STATUS' => 'pending',
            'RENTAL_TYPE' => $data['rental_type'],
            'DURATION_HOURS' => $priceData['duration_hours'],
            'COMMENT' => $data['comment'] ?? '',
            'LIBREBOOKING_RESERVATION_ID' => $data['librebooking_id'] ?? null
        ]);

        if ($result->isSuccess()) {
            return $result->getId();
        }

        return false;
    }

    public static function update($orderId, $data)
    {
        $updateData = [];
        $allowedFields = [
            'STATUS',
            'CLIENT_NAME',
            'CLIENT_PHONE',
            'CLIENT_EMAIL',
            'PRICE',
            'COMMENT',
            'PAYMENT_STATUS',
            'PAID_AMOUNT',
            'PAYMENT_ID',
            'CLIENT_TG_ID'
        ];

        foreach ($allowedFields as $field) {
            $fieldLower = strtolower($field);
            if (isset($data[$fieldLower])) {
                $updateData[$field] = $data[$fieldLower];
            }
        }

        if (isset($data['status'])) {
            $updateData['STATUS'] = $data['status'];
        }

        if (isset($data['extended_end_time'])) {
            $updateData['EXTENDED_END_TIME'] = new DateTime($data['extended_end_time']);
            $updateData['END_TIME'] = new DateTime($data['extended_end_time']);
        }

        $updateData['UPDATED_AT'] = new DateTime();

        if (empty($updateData)) {
            return true;
        }

        $result = OrderTable::update($orderId, $updateData);
        return $result->isSuccess();
    }

    public static function get($orderId)
    {
        $result = OrderTable::getById($orderId);
        return $result->fetch();
    }

    public static function getByOrderNumber($orderNumber)
    {
        $result = OrderTable::getList([
            'filter' => ['ORDER_NUMBER' => $orderNumber],
            'limit' => 1
        ]);
        return $result->fetch();
    }

    public static function getList($filter = [], $limit = 100, $offset = 0)
    {
        $params = [
            'filter' => $filter,
            'limit' => $limit,
            'offset' => $offset,
            'order' => ['ID' => 'DESC']
        ];

        $result = OrderTable::getList($params);
        $orders = [];
        while ($order = $result->fetch()) {
            $orders[] = $order;
        }
        return $orders;
    }

    public static function getListByPeriod($startDate, $endDate, $legalEntity = null)
    {
        $filter = [
            '>=CREATED_AT' => new DateTime($startDate . ' 00:00:00'),
            '<=CREATED_AT' => new DateTime($endDate . ' 23:59:59'),
            'DELETED_AT' => null
        ];

        if ($legalEntity) {
            $filter['LEGAL_ENTITY'] = $legalEntity;
        }

        return self::getList($filter, 1000, 0);
    }

    public static function softDelete($orderId, $userId = null)
    {
        $result = OrderTable::update($orderId, [
            'DELETED_AT' => new DateTime(),
            'DELETED_BY' => $userId ?: 0,
            'STATUS' => 'deleted'
        ]);

        if ($result->isSuccess()) {
            self::logAction('delete_order', $orderId, ['deleted_by' => $userId]);
            return true;
        }
        return false;
    }

    public static function extendTime($orderId, $newEndTime)
    {
        $order = self::get($orderId);
        if (!$order) {
            return ['success' => false, 'error' => 'Заказ не найден'];
        }

        $priceData = TariffManager::calculateExtensionPrice($orderId, $newEndTime);
        if (isset($priceData['error'])) {
            return $priceData;
        }

        $updateResult = self::update($orderId, [
            'extended_end_time' => $newEndTime,
            'price' => $priceData['new_total_price']
        ]);

        if ($updateResult) {
            self::logAction('extend_time', $orderId, ['new_end_time' => $newEndTime, 'additional_price' => $priceData['additional_price']]);
            return [
                'success' => true,
                'additional_price' => $priceData['additional_price'],
                'new_price' => $priceData['new_total_price']
            ];
        }

        return ['success' => false, 'error' => 'Ошибка обновления заказа'];
    }

    public static function updateStatus($orderId, $status)
    {
        self::logAction('status_change', $orderId, ['new_status' => $status]);
        return self::update($orderId, ['status' => $status]);
    }

    public static function updatePaymentInfo($orderId, $paymentId, $paymentStatus, $paidAmount)
    {
        self::logAction('payment_update', $orderId, ['payment_id' => $paymentId, 'status' => $paymentStatus]);
        return self::update($orderId, [
            'payment_id' => $paymentId,
            'payment_status' => $paymentStatus,
            'paid_amount' => $paidAmount
        ]);
    }

    public static function getPaymentInfo($orderId)
    {
        $order = self::get($orderId);
        if (!$order) return null;

        return [
            'order_id' => $order['ID'],
            'order_number' => $order['ORDER_NUMBER'],
            'payment_id' => $order['PAYMENT_ID'],
            'payment_status' => $order['PAYMENT_STATUS'],
            'paid_amount' => $order['PAID_AMOUNT'],
            'price' => $order['PRICE'],
            'deposit_amount' => $order['DEPOSIT_AMOUNT'],
            'legal_entity' => $order['LEGAL_ENTITY'],
            'requires_full_payment' => $order['PAID_AMOUNT'] < $order['PRICE']
        ];
    }

    private static function generateOrderNumber()
    {
        return 'ORD-' . date('YmdHis') . '-' . rand(1000, 9999);
    }

    private static function logAction($action, $orderId, $data)
    {
        global $DB;
        $DB->Insert('avs_booking_log', [
            'ACTION' => "'" . $DB->ForSql($action) . "'",
            'ORDER_ID' => intval($orderId),
            'MESSAGE' => "'" . $DB->ForSql(json_encode($data, JSON_UNESCAPED_UNICODE)) . "'",
            'IP_ADDRESS' => "'" . $DB->ForSql($_SERVER['REMOTE_ADDR'] ?? '') . "'",
            'CREATED_AT' => "'" . date('Y-m-d H:i:s') . "'"
        ]);
    }
}
