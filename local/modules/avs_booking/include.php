<?php

/**
 * Файл: /local/modules/avs_booking/include.php
 * Основной файл модуля
 */

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;

// Подключаем LibreBookingAPI из /local/php_interface/ если он там есть
$libreBookingApiPath = $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/LibreBookingAPI.php';
if (file_exists($libreBookingApiPath)) {
    require_once $libreBookingApiPath;
} else {
    // Создаем заглушку, если файла нет
    if (!class_exists('LibreBookingAPI')) {
        class LibreBookingAPI
        {
            public function __construct($apiUrl = null, $username = null, $password = null) {}
            public function authenticate()
            {
                return false;
            }
            public function checkAvailability($resourceId, $startTime, $endTime, $excludeReservationId = null)
            {
                return true;
            }
            public function createReservation($resourceId, $startTime, $endTime, $userData)
            {
                return null;
            }
            public function updateReservationTime($reservationId, $newEndTime)
            {
                return false;
            }
            public function moveReservation($reservationId, $newResourceId, $newStartTime, $newEndTime)
            {
                return false;
            }
            public function cancelReservation($reservationId)
            {
                return false;
            }
            public function getAvailableSlots($resourceId, $date)
            {
                return [];
            }
            public function getResourceInfo($resourceId)
            {
                return null;
            }
            public function getSessionToken()
            {
                return null;
            }
        }
    }
}

// Подключаем классы модуля
require_once __DIR__ . '/lib/OrderTable.php';
require_once __DIR__ . '/lib/Order.php';
require_once __DIR__ . '/lib/Api.php';
require_once __DIR__ . '/lib/Payment.php';
require_once __DIR__ . '/lib/TariffManager.php';
require_once __DIR__ . '/lib/NotificationService.php';
require_once __DIR__ . '/lib/DiscountManager.php';
require_once __DIR__ . '/lib/LibreBookingClient.php';
require_once __DIR__ . '/lib/YookassaHandler.php';
require_once __DIR__ . '/lib/OneCIntegration.php';

// Константы юридических лиц
define('AVS_LEGAL_BETON_SYSTEMS', 'beton_systems');
define('AVS_LEGAL_PARK_VICTORY', 'park_victory');

// Маппинг ID беседок к юридическим лицам
$GLOBALS['AVS_BOOKING_PAVILION_TO_LEGAL'] = [
    1 => AVS_LEGAL_BETON_SYSTEMS,      // Шарташ
    2 => AVS_LEGAL_BETON_SYSTEMS,      // Чемоданчик
    3 => AVS_LEGAL_PARK_VICTORY,       // Виктори парк
    4 => AVS_LEGAL_PARK_VICTORY,       // Виктори Озеро
];

// Беседки с повышенным авансом (ID)
$GLOBALS['AVS_BOOKING_HIGH_DEPOSIT'] = [5, 6]; // Теремок, Сибирская

/**
 * Класс AVSBookingModule - основной класс модуля
 */
class AVSBookingModule
{
    private static $moduleId = 'avs_booking';

    /**
     * Получение данных беседки из инфоблока
     * @param int $elementId ID элемента инфоблока
     * @return array|null
     */
    public static function getGazeboData($elementId)
    {
        if (!Loader::includeModule('iblock')) return null;

        $res = \CIBlockElement::GetList(
            [],
            ['ID' => (int)$elementId, 'ACTIVE' => 'Y'],
            false,
            false,
            [
                'ID',
                'NAME',
                'PROPERTY_LIBREBOOKING_RESOURCE_ID',
                'PROPERTY_PRICE_HOUR',
                'PROPERTY_PRICE',
                'PROPERTY_PRICE_NIGHT',
                'PROPERTY_DEPOSIT_AMOUNT'
            ]
        );

        if ($element = $res->Fetch()) {
            $deposit = (float)$element['PROPERTY_DEPOSIT_AMOUNT_VALUE'];
            if (!$deposit) {
                $deposit = self::getDefaultDeposit((int)$element['ID']);
            }

            return [
                'id' => (int)$element['ID'],
                'name' => (string)$element['NAME'],
                'resource_id' => (int)$element['PROPERTY_LIBREBOOKING_RESOURCE_ID_VALUE'],
                'hourly_price' => (float)$element['PROPERTY_PRICE_HOUR_VALUE'],
                'full_day_price' => (float)$element['PROPERTY_PRICE_VALUE'],
                'night_price' => (float)$element['PROPERTY_PRICE_NIGHT_VALUE'],
                'deposit_amount' => $deposit,
                'legal_entity' => self::getLegalEntityByPavilionId((int)$element['ID'])
            ];
        }
        return null;
    }

    /**
     * Получение дефолтной суммы аванса
     * @param int $pavilionId ID беседки
     * @return float
     */
    private static function getDefaultDeposit($pavilionId)
    {
        global $AVS_BOOKING_HIGH_DEPOSIT;

        if (in_array($pavilionId, $AVS_BOOKING_HIGH_DEPOSIT)) {
            return (float)Option::get(self::$moduleId, 'high_deposit_amount', 5000);
        }

        return (float)Option::get(self::$moduleId, 'default_deposit', 2000);
    }

    /**
     * Получение юридического лица по ID беседки
     * @param int $pavilionId ID беседки
     * @return string
     */
    public static function getLegalEntityByPavilionId($pavilionId)
    {
        global $AVS_BOOKING_PAVILION_TO_LEGAL;
        return $AVS_BOOKING_PAVILION_TO_LEGAL[$pavilionId] ?? AVS_LEGAL_BETON_SYSTEMS;
    }

    /**
     * Обновление цен беседки в инфоблоке
     * @param int $pavilionId ID беседки
     * @param array $prices Цены
     * @return bool
     */
    public static function updateGazeboPrices($pavilionId, $prices)
    {
        if (!Loader::includeModule('iblock')) return false;

        $props = [];
        if (isset($prices['hourly_price'])) $props['PRICE_HOUR'] = $prices['hourly_price'];
        if (isset($prices['full_day_price'])) $props['PRICE'] = $prices['full_day_price'];
        if (isset($prices['night_price'])) $props['PRICE_NIGHT'] = $prices['night_price'];

        if (empty($props)) return false;

        \CIBlockElement::SetPropertyValuesEx($pavilionId, 12, $props);
        return true;
    }

    /**
     * Проверка летнего периода для беседки
     * @param int $pavilionId ID беседки
     * @param string $date Дата
     * @return bool
     */
    public static function isSummerPeriod($pavilionId, $date)
    {
        $gazebo = self::getGazeboData($pavilionId);
        if (!$gazebo) return false;

        // Шарташ и Чемоданчик не переходят на летний период
        $noSummerParks = ['Шарташ', 'Чемоданчик'];
        if (in_array($gazebo['name'], $noSummerParks)) {
            return false;
        }

        $summerStart = Option::get(self::$moduleId, 'summer_period_start', date('Y') . '-06-01');
        $summerEnd = Option::get(self::$moduleId, 'summer_period_end', date('Y') . '-08-31');

        $dateObj = new DateTime($date);
        $start = new DateTime($summerStart);
        $end = new DateTime($summerEnd);

        return ($dateObj >= $start && $dateObj <= $end);
    }

    /**
     * Получение времени окончания работы
     * @param int $pavilionId ID беседки
     * @param string $date Дата
     * @return int
     */
    public static function getWorkEndHour($pavilionId, $date)
    {
        if (self::isSummerPeriod($pavilionId, $date)) {
            return (int)Option::get(self::$moduleId, 'summer_end_hour', 23);
        }
        return (int)Option::get(self::$moduleId, 'winter_end_hour', 22);
    }

    /**
     * Получение ограничений для даты
     * @param int $pavilionId ID беседки
     * @param string $date Дата
     * @return array
     */
    public static function getDateRestrictions($pavilionId, $date)
    {
        global $DB;

        $sql = "SELECT * FROM avs_booking_special_dates 
                WHERE PAVILION_ID = " . (int)$pavilionId . "
                AND DATE = '" . $DB->ForSql($date) . "'";

        $result = $DB->Query($sql);
        if ($row = $result->Fetch()) {
            return [
                'is_special' => true,
                'restriction_type' => $row['RESTRICTION_TYPE'],
                'allowed_types' => $row['ALLOWED_TYPES'] ? explode(',', $row['ALLOWED_TYPES']) : [],
                'price_modifier' => (float)$row['PRICE_MODIFIER'],
                'description' => $row['DESCRIPTION']
            ];
        }

        // Проверка праздничных дат
        $holidayDates = Option::get(self::$moduleId, 'holiday_dates', '');
        if ($holidayDates) {
            $holidays = explode(',', $holidayDates);
            if (in_array($date, $holidays)) {
                return [
                    'is_special' => true,
                    'restriction_type' => 'full_day_only',
                    'allowed_types' => ['full_day'],
                    'price_modifier' => (float)Option::get(self::$moduleId, 'weekend_price_modifier', 1.2),
                    'description' => 'Праздничный день'
                ];
            }
        }

        // Проверка выходных дней
        $weekendRestriction = Option::get(self::$moduleId, 'weekend_restriction', 'no');
        if ($weekendRestriction === 'full_day_only') {
            $dayOfWeek = date('N', strtotime($date));
            if ($dayOfWeek >= 6) {
                return [
                    'is_special' => true,
                    'restriction_type' => 'full_day_only',
                    'allowed_types' => ['full_day'],
                    'price_modifier' => (float)Option::get(self::$moduleId, 'weekend_price_modifier', 1.2),
                    'description' => 'Выходной день'
                ];
            }
        }

        return [
            'is_special' => false,
            'restriction_type' => 'standard',
            'allowed_types' => ['hourly', 'full_day', 'night'],
            'price_modifier' => 1,
            'description' => ''
        ];
    }

    /**
     * Получение доступных типов аренды
     * @param int $pavilionId ID беседки
     * @param string $date Дата
     * @return array
     */
    public static function getAvailableRentalTypes($pavilionId, $date)
    {
        $gazebo = self::getGazeboData($pavilionId);
        if (!$gazebo) return [];

        $restrictions = self::getDateRestrictions($pavilionId, $date);

        $allTypes = [
            'hourly' => ['price' => $gazebo['hourly_price'], 'label' => 'Почасовая аренда'],
            'full_day' => ['price' => $gazebo['full_day_price'], 'label' => 'Весь день'],
            'night' => ['price' => $gazebo['night_price'], 'label' => 'Ночь (01:00-09:00)']
        ];

        if ($restrictions['is_special'] && !empty($restrictions['allowed_types'])) {
            $allTypes = array_intersect_key($allTypes, array_flip($restrictions['allowed_types']));
        }

        foreach ($allTypes as $key => $type) {
            if ($type['price'] <= 0) {
                unset($allTypes[$key]);
            }
        }

        return $allTypes;
    }

    /**
     * Расчет временного диапазона
     * @param string $rentalType Тип аренды
     * @param string $date Дата
     * @param int $pavilionId ID беседки
     * @param int|null $startHour Час начала
     * @param int|null $hours Количество часов
     * @return array|null
     */
    public static function calculateTimeRange($rentalType, $date, $pavilionId, $startHour = null, $hours = null)
    {
        $timezone = '+05:00';
        $workEndHour = self::getWorkEndHour($pavilionId, $date);
        $minHours = (int)Option::get(self::$moduleId, 'min_hours', 4);

        switch ($rentalType) {
            case 'full_day':
                return [
                    'start' => $date . 'T10:00:00' . $timezone,
                    'end' => $date . 'T' . $workEndHour . ':00:00' . $timezone,
                    'duration' => $workEndHour - 10
                ];
            case 'night':
                return [
                    'start' => $date . 'T01:00:00' . $timezone,
                    'end' => $date . 'T09:00:00' . $timezone,
                    'duration' => 8
                ];
            case 'hourly':
                if ($hours < $minHours) {
                    return null;
                }
                $start = $date . 'T' . sprintf('%02d', $startHour) . ':00:00' . $timezone;
                $endHour = $startHour + $hours;
                if ($endHour > $workEndHour) {
                    return null;
                }
                return [
                    'start' => $start,
                    'end' => $date . 'T' . sprintf('%02d', $endHour) . ':00:00' . $timezone,
                    'duration' => $hours
                ];
            default:
                return null;
        }
    }

    /**
     * Создание заказа
     * @param array $data Данные заказа
     * @return int|false
     */
    public static function createOrder($data)
    {
        return \AVS\Booking\Order::create($data);
    }

    /**
     * Получение заказа по ID
     * @param int $orderId ID заказа
     * @return array|null
     */
    public static function getOrder($orderId)
    {
        return \AVS\Booking\Order::get($orderId);
    }

    /**
     * Получение списка заказов
     * @param array $filter Фильтр
     * @param int $limit Лимит
     * @return array
     */
    public static function getOrdersList($filter = [], $limit = 100)
    {
        return \AVS\Booking\Order::getList($filter, $limit, 0);
    }
}
