<?php

/**
 * Файл: /local/modules/avs_booking/lib/Order.php
 */

namespace AVS\Booking;

use Bitrix\Main\Type\DateTime;

class Order
{
    public static function create($data)
    {
        $legalEntity = \AVSBookingModule::getLegalEntityByPavilionId($data['pavilion_id']);
        $orderNumber = self::generateOrderNumber();

        $priceData = TariffManager::calculatePrice(
            $data['pavilion_id'],
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
            'DISCOUNT_CODE' => $data['discount_code'] ?? null,
            'STATUS' => $data['status'] ?? 'pending',
            'RENTAL_TYPE' => $data['rental_type'],
            'DURATION_HOURS' => $priceData['duration_hours'],
            'COMMENT' => $data['comment'] ?? '',
            'LIBREBOOKING_RESERVATION_ID' => $data['librebooking_id'] ?? null,
            'CREATED_AT' => new DateTime(),
            'UPDATED_AT' => new DateTime()
        ]);

        if ($result->isSuccess()) {
            $orderId = $result->getId();
            self::logAction('create_order', $orderId, $data);
            return $orderId;
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
        if ($result->isSuccess()) {
            self::logAction('update_order', $orderId, $updateData);
            return true;
        }

        return false;
    }

    public static function updateRekvizits($orderId, $updateData, $changes)
    {
        $order = self::get($orderId);
        if (!$order) {
            return false;
        }

        $data = [];

        if (isset($updateData['new_start_time'])) {
            $data['START_TIME'] = new DateTime($updateData['new_start_time']);
            $data['NEW_START_TIME'] = new DateTime($updateData['new_start_time']);
        }

        if (isset($updateData['new_end_time'])) {
            $data['END_TIME'] = new DateTime($updateData['new_end_time']);
            $data['NEW_END_TIME'] = new DateTime($updateData['new_end_time']);
        }

        if (isset($updateData['new_pavilion_id'])) {
            $data['PAVILION_ID'] = $updateData['new_pavilion_id'];
            $data['PAVILION_NAME'] = $updateData['new_pavilion_name'];
            $data['NEW_PAVILION_ID'] = $updateData['new_pavilion_id'];
            $data['NEW_PAVILION_NAME'] = $updateData['new_pavilion_name'];

            $legalEntity = \AVSBookingModule::getLegalEntityByPavilionId($updateData['new_pavilion_id']);
            $data['LEGAL_ENTITY'] = $legalEntity;
        }

        $data['UPDATED_AT'] = new DateTime();

        if (empty($data)) {
            return true;
        }

        $result = OrderTable::update($orderId, $data);

        if ($result->isSuccess()) {
            self::logAction('update_rekvizits', $orderId, $changes);

            if ($order['LIBREBOOKING_RESERVATION_ID'] && (isset($updateData['new_start_time']) || isset($updateData['new_end_time']) || isset($updateData['new_pavilion_id']))) {
                self::updateLibrebookingReservation($orderId, $updateData);
            }

            return true;
        }

        return false;
    }

    private static function updateLibrebookingReservation($orderId, $updateData)
    {
        $order = self::get($orderId);
        if (!$order || !$order['LIBREBOOKING_RESERVATION_ID']) {
            return false;
        }

        try {
            $client = new \AVSBookingLibreBookingClient();

            $startTime = $updateData['new_start_time'] ?? $order['START_TIME']->toString();
            $endTime = $updateData['new_end_time'] ?? $order['END_TIME']->toString();
            $pavilionId = $updateData['new_pavilion_id'] ?? $order['PAVILION_ID'];

            if ($pavilionId != $order['PAVILION_ID']) {
                $gazebo = \AVSBookingModule::getGazeboData($pavilionId);
                if ($gazebo && $gazebo['resource_id']) {
                    $client->moveReservation($order['LIBREBOOKING_RESERVATION_ID'], $gazebo['resource_id'], $startTime, $endTime);
                }
            } else {
                $client->updateReservationTime($order['LIBREBOOKING_RESERVATION_ID'], $endTime);
            }

            return true;
        } catch (\Exception $e) {
            self::logAction('librebooking_update_error', $orderId, ['error' => $e->getMessage()]);
            return false;
        }
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

    public static function getListByPeriod($startDate, $endDate, $filter = [])
    {
        $dateFilter = [
            '>=CREATED_AT' => new DateTime($startDate . ' 00:00:00'),
            '<=CREATED_AT' => new DateTime($endDate . ' 23:59:59'),
            'DELETED_AT' => null
        ];

        $allFilter = array_merge($dateFilter, $filter);

        return self::getList($allFilter, 1000, 0);
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

        if ($order['STATUS'] !== 'paid' && $order['STATUS'] !== 'active' && $order['STATUS'] !== 'confirmed') {
            return ['success' => false, 'error' => 'Продление возможно только для оплаченных или подтвержденных заказов'];
        }

        $priceData = TariffManager::calculateExtensionPrice($orderId, $newEndTime);
        if (isset($priceData['error'])) {
            return $priceData;
        }

        $updateResult = self::update($orderId, [
            'extended_end_time' => $newEndTime,
            'END_TIME' => $newEndTime,
            'PRICE' => $priceData['new_total_price']
        ]);

        if ($updateResult) {
            self::logAction('extend_time', $orderId, [
                'new_end_time' => $newEndTime,
                'additional_price' => $priceData['additional_price']
            ]);

            if ($order['LIBREBOOKING_RESERVATION_ID']) {
                self::updateLibrebookingReservation($orderId, ['new_end_time' => $newEndTime]);
            }

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
        $order = self::get($orderId);
        if (!$order) {
            return false;
        }

        $oldStatus = $order['STATUS'];

        $result = self::update($orderId, ['status' => $status]);

        if ($result) {
            self::logAction('status_change', $orderId, [
                'old_status' => $oldStatus,
                'new_status' => $status
            ]);

            if ($status == 'paid' && $oldStatus != 'paid') {
                $notification = new \AVSNotificationService();
                $notification->sendPaymentSuccessNotification($order);
            }

            if ($status == 'confirmed' && $oldStatus != 'confirmed') {
                $notification = new \AVSNotificationService();
                $notification->sendConfirmationNotification($order);
            }

            if ($status == 'cancelled' && $oldStatus != 'cancelled') {
                if ($order['LIBREBOOKING_RESERVATION_ID']) {
                    self::cancelLibrebookingReservation($order['LIBREBOOKING_RESERVATION_ID']);
                }
            }

            return true;
        }

        return false;
    }

    private static function cancelLibrebookingReservation($reservationId)
    {
        try {
            $client = new \AVSBookingLibreBookingClient();
            $client->cancelReservation($reservationId);
            return true;
        } catch (\Exception $e) {
            self::logAction('librebooking_cancel_error', 0, ['error' => $e->getMessage()]);
            return false;
        }
    }

    public static function updatePaymentInfo($orderId, $paymentId, $paymentStatus, $paidAmount)
    {
        $order = self::get($orderId);
        if (!$order) {
            return false;
        }

        $result = self::update($orderId, [
            'PAYMENT_ID' => $paymentId,
            'PAYMENT_STATUS' => $paymentStatus,
            'PAID_AMOUNT' => $paidAmount
        ]);

        if ($result) {
            self::logAction('payment_update', $orderId, [
                'payment_id' => $paymentId,
                'status' => $paymentStatus,
                'amount' => $paidAmount
            ]);

            if ($paymentStatus == 'succeeded' && $paidAmount >= $order['DEPOSIT_AMOUNT']) {
                self::updateStatus($orderId, 'paid');
            }

            return true;
        }

        return false;
    }

    public static function getPaymentInfo($orderId)
    {
        $order = self::get($orderId);
        if (!$order) return null;

        return [
            'order_id' => $order['ID'],
            'order_number' => $order['ORDER_NUMBER'],
            'pavilion_id' => $order['PAVILION_ID'],
            'pavilion_name' => $order['PAVILION_NAME'],
            'payment_id' => $order['PAYMENT_ID'],
            'payment_status' => $order['PAYMENT_STATUS'],
            'paid_amount' => (float)$order['PAID_AMOUNT'],
            'price' => (float)$order['PRICE'],
            'deposit_amount' => (float)$order['DEPOSIT_AMOUNT'],
            'legal_entity' => $order['LEGAL_ENTITY'],
            'requires_payment' => $order['PAID_AMOUNT'] < $order['PRICE'],
            'remaining_amount' => $order['PRICE'] - $order['PAID_AMOUNT']
        ];
    }

    private static function generateOrderNumber()
    {
        return 'ORD-' . date('YmdHis') . '-' . rand(1000, 9999);
    }

    private static function logAction($action, $orderId, $data)
    {
        global $DB;

        $message = json_encode($data, JSON_UNESCAPED_UNICODE);
        if (strlen($message) > 1000) {
            $message = substr($message, 0, 1000);
        }

        $DB->Insert('avs_booking_log', [
            'ACTION' => "'" . $DB->ForSql($action) . "'",
            'ORDER_ID' => intval($orderId),
            'USER_ID' => intval($GLOBALS['USER']->GetID() ?? 0),
            'MESSAGE' => "'" . $DB->ForSql($message) . "'",
            'IP_ADDRESS' => "'" . $DB->ForSql($_SERVER['REMOTE_ADDR'] ?? '') . "'",
            'CREATED_AT' => "'" . date('Y-m-d H:i:s') . "'"
        ]);
    }
}
