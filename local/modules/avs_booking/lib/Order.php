<?php
namespace AVS\Booking;

use Bitrix\Main\Type\DateTime;

class Order
{
    public static function create($data)
    {
        $legalEntity = self::getLegalEntityByPavilion($data['pavilion_id']);
        
        $orderNumber = self::generateOrderNumber();
        
        $result = OrderTable::add([
            'ORDER_NUMBER' => $orderNumber,
            'PAVILION_ID' => $data['pavilion_id'],
            'PAVILION_NAME' => $data['pavilion_name'],
            'LEGAL_ENTITY' => $legalEntity,
            'CLIENT_NAME' => $data['client_name'],
            'CLIENT_PHONE' => $data['client_phone'],
            'CLIENT_EMAIL' => $data['client_email'] ?? '',
            'START_TIME' => new DateTime($data['start_time']),
            'END_TIME' => new DateTime($data['end_time']),
            'PRICE' => $data['price'],
            'STATUS' => $data['status'] ?? 'pending',
            'COMMENT' => $data['comment'] ?? '',
            'LIBREBOOKING_RESERVATION_ID' => $data['librebooking_id'] ?? null,
            'RENTAL_TYPE' => $data['rental_type'] ?? '',
            'DURATION_HOURS' => $data['duration_hours'] ?? 0
        ]);
        
        if ($result->isSuccess()) {
            $orderId = $result->getId();
            return $orderId;
        }
        
        return false;
    }
    
    public static function update($orderId, $data)
    {
        $updateData = [];
        
        $allowedFields = ['STATUS', 'CLIENT_NAME', 'CLIENT_PHONE', 'CLIENT_EMAIL', 'PRICE', 'COMMENT', 'PAYMENT_STATUS', 'PAID_AMOUNT', 'PAYMENT_ID'];
        
        foreach ($allowedFields as $field) {
            $fieldLower = strtolower($field);
            if (isset($data[$fieldLower])) {
                $updateData[$field] = $data[$fieldLower];
            }
        }
        
        if (isset($data['extended_end_time'])) {
            $updateData['EXTENDED_END_TIME'] = new DateTime($data['extended_end_time']);
            $updateData['END_TIME'] = new DateTime($data['extended_end_time']);
        }
        
        if (isset($data['status'])) {
            $updateData['STATUS'] = $data['status'];
        }
        
        if (isset($data['payment_status'])) {
            $updateData['PAYMENT_STATUS'] = $data['payment_status'];
        }
        
        if (isset($data['paid_amount'])) {
            $updateData['PAID_AMOUNT'] = $data['paid_amount'];
        }
        
        if (isset($data['payment_id'])) {
            $updateData['PAYMENT_ID'] = $data['payment_id'];
        }
        
        $updateData['UPDATED_AT'] = new DateTime();
        
        if (empty($updateData)) {
            return true;
        }
        
        $result = OrderTable::update($orderId, $updateData);
        
        if ($result->isSuccess()) {
            return true;
        }
        
        return false;
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
            '>=CREATED_AT' => new DateTime($startDate),
            '<=CREATED_AT' => new DateTime($endDate)
        ];
        
        if ($legalEntity) {
            $filter['LEGAL_ENTITY'] = $legalEntity;
        }
        
        return self::getList($filter, 1000, 0);
    }
    
    public static function extendTime($orderId, $newEndTime)
    {
        $order = self::get($orderId);
        
        if (!$order) {
            return ['success' => false, 'error' => 'Заказ не найден'];
        }
        
        $workDayEnd = self::getWorkDayEnd($order['PAVILION_NAME']);
        $workDayEndTime = new \DateTime($workDayEnd);
        $newEnd = new \DateTime($newEndTime);
        
        if ($newEnd->format('H:i') > $workDayEndTime->format('H:i')) {
            return ['success' => false, 'error' => 'Время продления не может быть позже окончания рабочего дня'];
        }
        
        $currentEnd = new \DateTime($order['END_TIME']->toString());
        
        if ($newEnd <= $currentEnd) {
            return ['success' => false, 'error' => 'Новое время должно быть позже текущего'];
        }
        
        $additionalMinutes = ($newEnd->getTimestamp() - $currentEnd->getTimestamp()) / 60;
        $originalDuration = self::getDurationInHours($order['START_TIME']->toString(), $order['END_TIME']->toString());
        $hourlyRate = $order['PRICE'] / $originalDuration;
        $additionalPrice = ($hourlyRate / 60) * $additionalMinutes;
        $additionalPrice = round($additionalPrice, 2);
        
        $updateResult = self::update($orderId, [
            'extended_end_time' => $newEndTime,
            'price' => $order['PRICE'] + $additionalPrice
        ]);
        
        if ($updateResult) {
            if ($order['LIBREBOOKING_RESERVATION_ID']) {
                self::updateLibrebookingTime($order['LIBREBOOKING_RESERVATION_ID'], $newEndTime);
            }
            return ['success' => true, 'additional_price' => $additionalPrice, 'new_price' => $order['PRICE'] + $additionalPrice];
        }
        
        return ['success' => false, 'error' => 'Ошибка обновления заказа'];
    }
    
    private static function getLegalEntityByPavilion($pavilionId)
    {
        global $AVS_BOOKING_PAVILION_TO_LEGAL;
        return $AVS_BOOKING_PAVILION_TO_LEGAL[$pavilionId] ?? AVS_LEGAL_BETON_SYSTEMS;
    }
    
    private static function generateOrderNumber()
    {
        return 'ORD-' . date('YmdHis') . '-' . rand(1000, 9999);
    }
    
    private static function getWorkDayEnd($pavilionName)
    {
        $gazebo = \AVSBookingModule::getGazeboDataByName($pavilionName);
        if ($gazebo) {
            $date = date('Y-m-d');
            $endHour = \AVSBookingModule::getWorkEndHour($gazebo['id'], $date);
            return date('Y-m-d') . ' ' . $endHour . ':00:00';
        }
        return date('Y-m-d') . ' 22:00:00';
    }
    
    private static function getDurationInHours($start, $end)
    {
        $startDate = new \DateTime($start);
        $endDate = new \DateTime($end);
        $diff = $startDate->diff($endDate);
        return $diff->h + ($diff->i / 60);
    }
    
    private static function updateLibrebookingTime($reservationId, $newEndTime)
    {
        try {
            $api = \AVSBookingModule::getApiClient();
            $api->updateReservationTime($reservationId, $newEndTime);
        } catch (\Exception $e) {
            // Log error
        }
    }
}

// Добавляем метод в AVSBookingModule
function AVSBookingModule::getGazeboDataByName($name)
{
    if (!Loader::includeModule('iblock')) {
        return null;
    }

    $res = \CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => 12, 'NAME' => $name, 'ACTIVE' => 'Y'],
        false,
        ['nTopCount' => 1],
        [
            'ID',
            'NAME',
            'PROPERTY_LIBREBOOKING_RESOURCE_ID',
            'PROPERTY_PRICE_HOUR',
            'PROPERTY_PRICE',
            'PROPERTY_PRICE_NIGHT',
            'PROPERTY_NIGHT_SEASON_START',
            'PROPERTY_NIGHT_SEASON_END',
            'PROPERTY_DEPOSIT_AMOUNT',
            'PROPERTY_MIN_HOURS',
            'PROPERTY_LEGAL_ENTITY'
        ]
    );

    if ($element = $res->Fetch()) {
        return [
            'id' => (int)$element['ID'],
            'name' => (string)$element['NAME'],
            'resource_id' => (int)$element['PROPERTY_LIBREBOOKING_RESOURCE_ID_VALUE'],
            'hourly_price' => (float)$element['PROPERTY_PRICE_HOUR_VALUE'],
            'full_day_price' => (float)$element['PROPERTY_PRICE_VALUE'],
            'night_price' => (float)$element['PROPERTY_PRICE_NIGHT_VALUE'],
            'night_season_start' => $element['PROPERTY_NIGHT_SEASON_START_VALUE'],
            'night_season_end' => $element['PROPERTY_NIGHT_SEASON_END_VALUE'],
            'deposit_amount' => (float)$element['PROPERTY_DEPOSIT_AMOUNT_VALUE'],
            'min_hours' => (int)$element['PROPERTY_MIN_HOURS_VALUE'] ?: 4,
            'legal_entity' => $element['PROPERTY_LEGAL_ENTITY_VALUE']
        ];
    }

    return null;
}