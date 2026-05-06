<?php
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
$arResult['RENTAL_TYPES'] = [];

$request = Context::getCurrent()->getRequest();

if ($request->isPost() && check_bitrix_sessid()) {
    $rentalType = $request->getPost('rental_type');
    $date = $request->getPost('date');
    $startHour = $request->getPost('start_hour');
    $hours = $request->getPost('hours');
    $clientName = trim($request->getPost('client_name'));
    $clientPhone = trim($request->getPost('client_phone'));
    $clientEmail = trim($request->getPost('client_email'));
    $comment = trim($request->getPost('comment'));

    $errors = [];

    if (empty($clientName)) {
        $errors[] = 'Введите имя';
    }

    if (empty($clientPhone)) {
        $errors[] = 'Введите телефон';
    }

    if (!$date) {
        $errors[] = 'Выберите дату';
    }

    if ($rentalType == 'hourly') {
        if ($startHour === null) {
            $errors[] = 'Выберите время начала';
        }
        if (!$hours) {
            $errors[] = 'Выберите продолжительность';
        }
    }

    if (empty($errors)) {
        $timeRange = AVSBookingModule::calculateTimeRange($rentalType, $date, $elementId, $startHour, $hours);

        if ($timeRange) {
            $available = AVSBookingModule::checkAvailability($gazebo['resource_id'], $timeRange['start'], $timeRange['end']);

            if ($available) {
                $price = AVSBookingModule::getPriceForDate($elementId, $date, $rentalType);

                if ($rentalType == 'hourly' && $hours) {
                    $price = $price * $hours;
                }

                $bookingData = [
                    'resource_id' => $gazebo['resource_id'],
                    'start_time' => $timeRange['start'],
                    'end_time' => $timeRange['end'],
                    'client_name' => $clientName,
                    'client_phone' => $clientPhone,
                    'client_email' => $clientEmail,
                    'comment' => $comment,
                    'pavilion_id' => $elementId,
                    'pavilion_name' => $gazebo['name'],
                    'price' => $price,
                    'rental_type' => $rentalType,
                    'duration_hours' => $rentalType == 'hourly' ? $hours : 0
                ];

                $reservationId = AVSBookingModule::createBooking(
                    $gazebo['resource_id'],
                    $timeRange['start'],
                    $timeRange['end'],
                    $bookingData
                );

                if ($reservationId) {
                    $bookingData['librebooking_id'] = $reservationId;

                    $orderId = AVSBookingModule::createOrder($bookingData);

                    if ($orderId) {
                        AVSBookingModule::sendNotifications($orderId, $bookingData, $price);

                        LocalRedirect($arParams['SUCCESS_PAGE'] . '?order_id=' . $orderId);
                    } else {
                        $errors[] = 'Ошибка создания заказа';
                    }
                } else {
                    $errors[] = 'Ошибка создания бронирования';
                }
            } else {
                $errors[] = 'Выбранное время уже занято';
            }
        } else {
            $errors[] = 'Ошибка расчета времени';
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
        ];
    }
}

$arResult['AVAILABLE_SLOTS'] = [];

$selectedDate = $request->getPost('date') ?: date('Y-m-d');
$arResult['SELECTED_DATE'] = $selectedDate;

$availableTypes = AVSBookingModule::getAvailableRentalTypes($elementId, $selectedDate);
$arResult['RENTAL_TYPES'] = $availableTypes;

if (isset($availableTypes['hourly'])) {
    $slots = AVSBookingModule::getAvailableSlots($gazebo['resource_id'], $selectedDate);
    $arResult['AVAILABLE_SLOTS'] = $slots;
}

$this->includeComponentTemplate();
