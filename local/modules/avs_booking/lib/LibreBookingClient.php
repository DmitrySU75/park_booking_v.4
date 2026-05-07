<?php

/**
 * Файл: /local/modules/avs_booking/lib/LibreBookingClient.php
 * Обертка для совместимости с LibreBookingAPI из /local/php_interface/
 */

// Подключаем основной класс, если он еще не загружен
if (!class_exists('LibreBookingAPI')) {
    $libreBookingApiPath = $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/LibreBookingAPI.php';
    if (file_exists($libreBookingApiPath)) {
        require_once $libreBookingApiPath;
    } else {
        throw new Exception('LibreBookingAPI class not found. Please ensure /local/php_interface/LibreBookingAPI.php exists.');
    }
}

/**
 * Class AVSBookingLibreBookingClient
 * Расширяет LibreBookingAPI и добавляет специфичные для модуля методы
 */
class AVSBookingLibreBookingClient extends LibreBookingAPI
{
    /**
     * Конструктор - вызывает родительский конструктор
     * @param string|null $apiUrl URL API
     * @param string|null $username Логин
     * @param string|null $password Пароль
     */
    public function __construct($apiUrl = null, $username = null, $password = null)
    {
        parent::__construct($apiUrl, $username, $password);
    }

    /**
     * Проверка доступности с логированием
     * @param int $resourceId ID ресурса
     * @param string $startTime Начало
     * @param string $endTime Конец
     * @param int|null $excludeReservationId ID для исключения
     * @return bool
     */
    public function checkAvailabilityWithLog($resourceId, $startTime, $endTime, $excludeReservationId = null)
    {
        $result = $this->checkAvailability($resourceId, $startTime, $endTime, $excludeReservationId);

        // Логируем результат
        global $DB;
        if ($DB && $DB->TableExists('avs_booking_log')) {
            $DB->Insert('avs_booking_log', [
                'ACTION' => "'check_availability'",
                'MESSAGE' => "'Resource: {$resourceId}, Start: {$startTime}, End: {$endTime}, Result: " . ($result ? 'available' : 'not_available') . "'",
                'CREATED_AT' => "'" . date('Y-m-d H:i:s') . "'"
            ]);
        }

        return $result;
    }

    /**
     * Создание бронирования с логированием
     * @param int $resourceId ID ресурса
     * @param string $startTime Начало
     * @param string $endTime Конец
     * @param array $userData Данные пользователя
     * @return int|null
     * @throws Exception
     */
    public function createReservationWithLog($resourceId, $startTime, $endTime, $userData)
    {
        try {
            $result = $this->createReservation($resourceId, $startTime, $endTime, $userData);

            // Логируем результат
            global $DB;
            if ($DB && $DB->TableExists('avs_booking_log')) {
                $DB->Insert('avs_booking_log', [
                    'ACTION' => "'create_reservation'",
                    'MESSAGE' => "'Resource: {$resourceId}, User: " . ($userData['name'] ?? '') . ", Result: " . ($result ? $result : 'failed') . "'",
                    'CREATED_AT' => "'" . date('Y-m-d H:i:s') . "'"
                ]);
            }

            return $result;
        } catch (Exception $e) {
            global $DB;
            if ($DB && $DB->TableExists('avs_booking_log')) {
                $DB->Insert('avs_booking_log', [
                    'ACTION' => "'create_reservation_error'",
                    'MESSAGE' => "'" . $DB->ForSql($e->getMessage()) . "'",
                    'CREATED_AT' => "'" . date('Y-m-d H:i:s') . "'"
                ]);
            }
            throw $e;
        }
    }

    /**
     * Проверка доступности для всей дневной аренды
     * @param int $resourceId ID ресурса
     * @param string $date Дата
     * @param int $pavilionId ID беседки
     * @return bool
     */
    public function checkFullDayAvailability($resourceId, $date, $pavilionId)
    {
        $workEndHour = \AVSBookingModule::getWorkEndHour($pavilionId, $date);
        $timezone = '+05:00';
        $startTime = $date . 'T10:00:00' . $timezone;
        $endTime = $date . 'T' . $workEndHour . ':00:00' . $timezone;

        return $this->checkAvailability($resourceId, $startTime, $endTime);
    }

    /**
     * Проверка доступности для ночной аренды
     * @param int $resourceId ID ресурса
     * @param string $date Дата
     * @return bool
     */
    public function checkNightAvailability($resourceId, $date)
    {
        $timezone = '+05:00';
        $startTime = $date . 'T01:00:00' . $timezone;
        $endTime = $date . 'T09:00:00' . $timezone;

        return $this->checkAvailability($resourceId, $startTime, $endTime);
    }

    /**
     * Проверка доступности для почасовой аренды с минимальным периодом
     * @param int $resourceId ID ресурса
     * @param string $date Дата
     * @param int $startHour Час начала
     * @param int|null $hours Количество часов
     * @return bool
     */
    public function checkHourlyAvailability($resourceId, $date, $startHour, $hours = null)
    {
        $minHours = (int)\Bitrix\Main\Config\Option::get('avs_booking', 'min_hours', 4);
        if ($hours === null) {
            $hours = $minHours;
        }

        if ($hours < $minHours) {
            return false;
        }

        $endHour = $startHour + $hours;
        $timezone = '+05:00';
        $startTime = $date . 'T' . sprintf('%02d', $startHour) . ':00:00' . $timezone;
        $endTime = $date . 'T' . sprintf('%02d', $endHour) . ':00:00' . $timezone;

        return $this->checkAvailability($resourceId, $startTime, $endTime);
    }
}
