<?php

/**
 * Файл: /local/php_interface/init.php
 * Обработчики событий и дополнительные функции
 */

// Подключаем класс API LibreBooking
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
        $startHour = $_REQUEST['start_hour'] ?? '';
        $hours = (int)($_REQUEST['hours'] ?? 0);
        $rentalType = $_REQUEST['rental_type'] ?? 'hourly';

        if (empty($date)) {
            return;
        }

        // Сохраняем дату в сессию
        $_SESSION['avs_booking_filter_date'] = $date;

        // Получаем доступные беседки в зависимости от типа аренды
        if ($rentalType == 'hourly' && $startHour && $hours) {
            $availablePavilions = getAvailablePavilionsForHourly($date, $startHour, $hours);
        } elseif ($rentalType == 'full_day') {
            $availablePavilions = getAvailablePavilionsForFullDay($date);
        } elseif ($rentalType == 'night') {
            $availablePavilions = getAvailablePavilionsForNight($date);
        } else {
            return;
        }

        // Применяем фильтр
        if (!empty($availablePavilions)) {
            $arFilter['ID'] = $availablePavilions;
        }
    }

    /**
     * Получение доступных беседок для почасовой аренды
     */
    function getAvailablePavilionsForHourly($date, $startHour, $hours)
    {
        global $DB;

        $minHours = (int)\Bitrix\Main\Config\Option::get('avs_booking', 'min_hours', 4);
        if ($hours < $minHours) {
            return [];
        }

        $endHour = $startHour + $hours;
        $timezone = '+05:00';
        $startTime = $date . 'T' . sprintf('%02d', $startHour) . ':00:00' . $timezone;
        $endTime = $date . 'T' . sprintf('%02d', $endHour) . ':00:00' . $timezone;

        $sql = "SELECT DISTINCT eb.ID, eb.PROPERTY_LIBREBOOKING_RESOURCE_ID_VALUE as resource_id
                FROM b_iblock_element eb
                WHERE eb.IBLOCK_ID = 12 AND eb.ACTIVE = 'Y'";

        $result = $DB->Query($sql);
        $available = [];

        try {
            $api = new LibreBookingAPI();

            while ($row = $result->Fetch()) {
                if ($row['resource_id']) {
                    if ($api->checkAvailability($row['resource_id'], $startTime, $endTime)) {
                        $available[] = $row['ID'];
                    }
                } else {
                    $available[] = $row['ID'];
                }
            }
        } catch (Exception $e) {
            // При ошибке API возвращаем все беседки
            $result = $DB->Query($sql);
            while ($row = $result->Fetch()) {
                $available[] = $row['ID'];
            }
        }

        return $available;
    }

    /**
     * Получение доступных беседок на весь день
     */
    function getAvailablePavilionsForFullDay($date)
    {
        global $DB;

        $sql = "SELECT DISTINCT eb.ID, eb.PROPERTY_LIBREBOOKING_RESOURCE_ID_VALUE as resource_id
                FROM b_iblock_element eb
                WHERE eb.IBLOCK_ID = 12 AND eb.ACTIVE = 'Y'";

        $result = $DB->Query($sql);
        $available = [];

        try {
            $api = new LibreBookingAPI();
            $timezone = '+05:00';

            while ($row = $result->Fetch()) {
                $gazebo = AVSBookingModule::getGazeboData($row['ID']);
                if ($gazebo) {
                    $workEndHour = AVSBookingModule::getWorkEndHour($row['ID'], $date);
                    $startTime = $date . 'T10:00:00' . $timezone;
                    $endTime = $date . 'T' . $workEndHour . ':00:00' . $timezone;

                    if ($row['resource_id']) {
                        if ($api->checkAvailability($row['resource_id'], $startTime, $endTime)) {
                            $available[] = $row['ID'];
                        }
                    } else {
                        $available[] = $row['ID'];
                    }
                }
            }
        } catch (Exception $e) {
            $result = $DB->Query($sql);
            while ($row = $result->Fetch()) {
                $available[] = $row['ID'];
            }
        }

        return $available;
    }

    /**
     * Получение доступных беседок на ночь
     */
    function getAvailablePavilionsForNight($date)
    {
        global $DB;

        $timezone = '+05:00';
        $startTime = $date . 'T01:00:00' . $timezone;
        $endTime = $date . 'T09:00:00' . $timezone;

        $sql = "SELECT DISTINCT eb.ID, eb.PROPERTY_LIBREBOOKING_RESOURCE_ID_VALUE as resource_id
                FROM b_iblock_element eb
                WHERE eb.IBLOCK_ID = 12 AND eb.ACTIVE = 'Y'";

        $result = $DB->Query($sql);
        $available = [];

        try {
            $api = new LibreBookingAPI();

            while ($row = $result->Fetch()) {
                if ($row['resource_id']) {
                    if ($api->checkAvailability($row['resource_id'], $startTime, $endTime)) {
                        $available[] = $row['ID'];
                    }
                } else {
                    $available[] = $row['ID'];
                }
            }
        } catch (Exception $e) {
            $result = $DB->Query($sql);
            while ($row = $result->Fetch()) {
                $available[] = $row['ID'];
            }
        }

        return $available;
    }

    /**
     * AJAX обработчик для получения доступных слотов
     */
    if (isset($_REQUEST['ajax_action']) && $_REQUEST['ajax_action'] == 'get_available_slots') {
        $resourceId = (int)($_REQUEST['resource_id'] ?? 0);
        $date = $_REQUEST['date'] ?? '';

        if ($resourceId && $date) {
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

    /**
     * AJAX обработчик для проверки доступности
     */
    if (isset($_REQUEST['ajax_action']) && $_REQUEST['ajax_action'] == 'check_availability_ajax') {
        $resourceId = (int)($_REQUEST['resource_id'] ?? 0);
        $startTime = $_REQUEST['start_time'] ?? '';
        $endTime = $_REQUEST['end_time'] ?? '';

        if ($resourceId && $startTime && $endTime) {
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
}
