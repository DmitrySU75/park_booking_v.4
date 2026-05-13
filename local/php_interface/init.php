<?php

/**
 * Файл: /local/php_interface/init.php
 * Инициализация сайта - подключаем API и обработчики
 */

// Подключаем класс API LibreBooking (ОБЯЗАТЕЛЬНО)
require_once __DIR__ . '/LibreBookingAPI.php';

// Проверяем, установлен ли модуль avs_booking
if (\Bitrix\Main\Loader::includeModule('avs_booking')) {

    /**
     * Обработчик для SmartFilter - фильтрация беседок по доступности
     */
    AddEventHandler('iblock', 'OnIBlockElementBuildFilter', 'OnIBlockElementBuildFilterHandler');

    function OnIBlockElementBuildFilterHandler(&$arFilter, $arParams)
    {
        // Проверяем, что фильтруем инфоблок беседок (ID = 12)
        if (($arParams['IBLOCK_ID'] ?? 0) != 12 && ($arFilter['IBLOCK_ID'] ?? 0) != 12) {
            return;
        }

        // Получаем параметры фильтрации
        $date = $_REQUEST['date'] ?? $_SESSION['avs_booking_filter_date'] ?? '';
        $rentalType = $_REQUEST['rental_type'] ?? 'hourly';
        $startHour = $_REQUEST['start_hour'] ?? '';
        $hours = (int)($_REQUEST['hours'] ?? 0);

        if (empty($date)) {
            return;
        }

        // Сохраняем дату в сессию
        $_SESSION['avs_booking_filter_date'] = $date;

        // Получаем доступные беседки
        $availablePavilions = [];

        // Для почасовой аренды проверяем доступность конкретного временного слота
        if ($rentalType == 'hourly' && $startHour && $hours >= 4) {
            $timezone = '+05:00';
            $startTime = $date . 'T' . sprintf('%02d', $startHour) . ':00:00' . $timezone;
            $endTime = $date . 'T' . sprintf('%02d', $startHour + $hours) . ':00:00' . $timezone;

            $res = \CIBlockElement::GetList(
                [],
                ['IBLOCK_ID' => 12, 'ACTIVE' => 'Y'],
                false,
                false,
                ['ID', 'PROPERTY_LIBREBOOKING_RESOURCE_ID']
            );

            $api = new LibreBookingAPI();

            while ($el = $res->Fetch()) {
                $resourceId = (int)$el['PROPERTY_LIBREBOOKING_RESOURCE_ID_VALUE'];
                
                if ($resourceId) {
                    try {
                        if ($api->checkAvailability($resourceId, $startTime, $endTime)) {
                            $availablePavilions[] = $el['ID'];
                        }
                    } catch (Exception $e) {
                        // При ошибке API не фильтруем эту беседку
                        $availablePavilions[] = $el['ID'];
                    }
                } else {
                    // Если нет ID ресурса, считаем беседку доступной
                    $availablePavilions[] = $el['ID'];
                }
            }

            if (!empty($availablePavilions)) {
                $arFilter['ID'] = $availablePavilions;
            }
        }
        
        // Для полного дня и ночи можно добавить аналогичную логику
    }
}

// Обработчик AJAX-запросов для получения доступных слотов (опционально)
if (isset($_REQUEST['ajax_action']) && $_REQUEST['ajax_action'] == 'get_available_slots') {
    $resourceId = (int)($_REQUEST['resource_id'] ?? 0);
    $date = $_REQUEST['date'] ?? '';

    if ($resourceId && $date && \Bitrix\Main\Loader::includeModule('avs_booking')) {
        try {
            $api = new LibreBookingAPI();
            $slots = $api->getAvailableSlots($resourceId, $date);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'slots' => $slots]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// Обработчик AJAX-запросов для проверки доступности (опционально)
if (isset($_REQUEST['ajax_action']) && $_REQUEST['ajax_action'] == 'check_availability_ajax') {
    $resourceId = (int)($_REQUEST['resource_id'] ?? 0);
    $startTime = $_REQUEST['start_time'] ?? '';
    $endTime = $_REQUEST['end_time'] ?? '';

    if ($resourceId && $startTime && $endTime && \Bitrix\Main\Loader::includeModule('avs_booking')) {
        try {
            $api = new LibreBookingAPI();
            $available = $api->checkAvailability($resourceId, $startTime, $endTime);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'available' => $available]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}