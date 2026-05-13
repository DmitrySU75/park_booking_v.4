<?php

/**
 * Файл: /local/modules/avs_booking/bitrix/components/avs_booking/booking.form/component.php
 */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Context;
use Bitrix\Main\Loader;

if (!Loader::includeModule('avs_booking')) {
    ShowError('Модуль avs_booking не установлен');
    return;
}

$elementId = intval($arParams['ELEMENT_ID']);
if (!$elementId) {
    ShowError('Не указан ID беседки');
    return;
}

$gazebo = AVSBookingModule::getGazeboData($elementId);
if (!$gazebo) {
    ShowError('Беседка не найдена');
    return;
}

$arResult['GAZEBO'] = $gazebo;
$request = Context::getCurrent()->getRequest();

// Обработка POST-запроса
if ($request->isPost() && check_bitrix_sessid()) {
    $rentalType = $request->getPost('rental_type');
    $date = $request->getPost('date');
    $startHour = $request->getPost('start_hour');
    $hours = $request->getPost('hours');
    $clientName = trim($request->getPost('client_name'));
    $clientPhone = trim($request->getPost('client_phone'));
    $clientEmail = trim($request->getPost('client_email'));
    $comment = trim($request->getPost('comment'));
    $discountCode = trim($request->getPost('discount_code'));

    $errors = [];

    if (empty($clientName)) $errors[] = 'Введите имя';
    if (empty($clientPhone)) $errors[] = 'Введите телефон';
    if (!$date) $errors[] = 'Выберите дату';

    if ($rentalType == 'hourly') {
        if ($startHour === null) $errors[] = 'Выберите время начала';
        if (!$hours) $errors[] = 'Выберите продолжительность';
    }

    if (empty($errors)) {
        global $DB;
        $DB->StartTransaction();
        
        try {
            $timeRange = AVSBookingModule::calculateTimeRange($rentalType, $date, $elementId, $startHour, $hours);

            if (!$timeRange) {
                throw new Exception('Выбранное время выходит за пределы времени работы беседки');
            }

            $priceData = \AVS\Booking\TariffManager::calculatePrice($elementId, $rentalType, $date, $hours, $discountCode);

            if (isset($priceData['error'])) {
                throw new Exception($priceData['error']);
            }

            $available = true;
            if ($gazebo['resource_id']) {
                $client = new AVSBookingLibreBookingClient();
                $available = $client->checkAvailability($gazebo['resource_id'], $timeRange['start'], $timeRange['end']);
            }

            if (!$available) {
                throw new Exception('Выбранное время уже занято');
            }

            $bookingData = [
                'pavilion_id' => $elementId,
                'pavilion_name' => $gazebo['name'],
                'client_name' => $clientName,
                'client_phone' => $clientPhone,
                'client_email' => $clientEmail,
                'start_time' => $timeRange['start'],
                'end_time' => $timeRange['end'],
                'price' => $priceData['total_price'],
                'rental_type' => $rentalType,
                'duration_hours' => $priceData['duration_hours'],
                'comment' => $comment,
                'discount_code' => $discountCode
            ];

            $reservationId = null;
            if ($gazebo['resource_id']) {
                $userData = [
                    'name' => $clientName,
                    'phone' => $clientPhone,
                    'email' => $clientEmail,
                    'comment' => $comment
                ];
                $client = new AVSBookingLibreBookingClient();
                $reservationId = $client->createReservation($gazebo['resource_id'], $timeRange['start'], $timeRange['end'], $userData);
                
                if (!$reservationId) {
                    throw new Exception('Ошибка создания бронирования в системе');
                }
                $bookingData['librebooking_id'] = $reservationId;
            }

            $orderId = AVSBookingModule::createOrder($bookingData);

            if (!$orderId) {
                throw new Exception('Ошибка создания заказа');
            }

            $DB->Commit();
            
            LocalRedirect($arParams['SUCCESS_PAGE'] . '?order_id=' . $orderId);
            
        } catch (Exception $e) {
            $DB->Rollback();
            $errors[] = $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $arResult['ERRORS'] = $errors;
        $arResult['POST'] = [
            'rental_type' => $rentalType,
            'date' => $date,
            'start_hour' => $startHour,
            'hours' => $hours,
            'client_name' => $clientName,
            'client_phone' => $clientPhone,
            'client_email' => $clientEmail,
            'comment' => $comment,
            'discount_code' => $discountCode
        ];
    }
}

// Подготовка данных для отображения формы
$selectedDate = $request->getPost('date') ?: date('Y-m-d');
$arResult['SELECTED_DATE'] = $selectedDate;
$arResult['RENTAL_TYPES'] = AVSBookingModule::getAvailableRentalTypes($elementId, $selectedDate);
$arResult['WORK_END_HOUR'] = AVSBookingModule::getWorkEndHour($elementId, $selectedDate);
$arResult['MIN_HOURS'] = (int)\Bitrix\Main\Config\Option::get('avs_booking', 'min_hours', 4);
$arResult['MAX_HOURS'] = $arResult['WORK_END_HOUR'] - 10;

if (isset($arResult['RENTAL_TYPES']['hourly'])) {
    $slots = [];
    $minHours = $arResult['MIN_HOURS'];
    
    for ($hour = 10; $hour <= $arResult['WORK_END_HOUR'] - $minHours; $hour++) {
        $maxPossibleHours = $arResult['WORK_END_HOUR'] - $hour;
        $slots[] = [
            'hour' => $hour,
            'label' => $hour . ':00',
            'max_hours' => $maxPossibleHours
        ];
    }
    $arResult['AVAILABLE_SLOTS'] = $slots;
}

$this->includeComponentTemplate();
