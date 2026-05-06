<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;

require_once __DIR__ . '/lib/OrderTable.php';
require_once __DIR__ . '/lib/Order.php';
require_once __DIR__ . '/lib/Api.php';
require_once __DIR__ . '/lib/Payment.php';
require_once __DIR__ . '/lib/TariffManager.php';
require_once __DIR__ . '/lib/NotificationService.php';
require_once __DIR__ . '/lib/DiscountManager.php';
require_once __DIR__ . '/lib/AvailabilityChecker.php';
require_once __DIR__ . '/lib/LibreBookingClient.php';
require_once __DIR__ . '/lib/YookassaHandler.php';
require_once __DIR__ . '/lib/OneCIntegration.php';

define('AVS_LEGAL_BETON_SYSTEMS', 'beton_systems');
define('AVS_LEGAL_PARK_VICTORY', 'park_victory');

// Маппинг беседок к юридическим лицам
$GLOBALS['AVS_BOOKING_PAVILION_TO_LEGAL'] = [
    'Шарташ' => AVS_LEGAL_BETON_SYSTEMS,
    'Чемоданчик' => AVS_LEGAL_BETON_SYSTEMS,
    'Виктори парк' => AVS_LEGAL_PARK_VICTORY,
    'Виктори Озеро' => AVS_LEGAL_PARK_VICTORY,
];

// Беседки с повышенным авансом
$GLOBALS['AVS_BOOKING_HIGH_DEPOSIT'] = ['Теремок', 'Сибирская'];

class AVSBookingModule
{
    private static $moduleId = 'avs_booking';

    /**
     * Получение данных беседки из инфоблока
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
                $deposit = self::getDefaultDeposit($element['NAME']);
            }

            return [
                'id' => (int)$element['ID'],
                'name' => (string)$element['NAME'],
                'resource_id' => (int)$element['PROPERTY_LIBREBOOKING_RESOURCE_ID_VALUE'],
                'hourly_price' => (float)$element['PROPERTY_PRICE_HOUR_VALUE'],
                'full_day_price' => (float)$element['PROPERTY_PRICE_VALUE'],
                'night_price' => (float)$element['PROPERTY_PRICE_NIGHT_VALUE'],
                'deposit_amount' => $deposit,
                'legal_entity' => self::getLegalEntityByPavilion($element['NAME'])
            ];
        }
        return null;
    }

    /**
     * Получение дефолтной суммы аванса
     */
    private static function getDefaultDeposit($pavilionName)
    {
        global $AVS_BOOKING_HIGH_DEPOSIT;

        $highDepositList = Option::get(self::$moduleId, 'high_deposit_pavilions', '');
        $highDepositArray = array_map('trim', explode(',', $highDepositList));

        if (in_array($pavilionName, $highDepositArray)) {
            return (float)Option::get(self::$moduleId, 'high_deposit_amount', 5000);
        }

        return (float)Option::get(self::$moduleId, 'default_deposit', 2000);
    }

    /**
     * Получение юридического лица по беседке
     */
    public static function getLegalEntityByPavilion($pavilionName)
    {
        global $AVS_BOOKING_PAVILION_TO_LEGAL;
        return $AVS_BOOKING_PAVILION_TO_LEGAL[$pavilionName] ?? AVS_LEGAL_BETON_SYSTEMS;
    }

    /**
     * Проверка летнего периода для беседки
     */
    public static function isSummerPeriod($pavilionName, $date)
    {
        // Шарташ и Чемоданчик не переходят на летний период
        $noSummerParks = ['Шарташ', 'Чемоданчик'];
        if (in_array($pavilionName, $noSummerParks)) {
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
     */
    public static function getWorkEndHour($pavilionName, $date)
    {
        if (self::isSummerPeriod($pavilionName, $date)) {
            return 23; // Летний период до 23:00
        }
        return 22; // Зимний период до 22:00
    }

    /**
     * Расчет времени аренды
     */
    public static function calculateTimeRange($rentalType, $date, $pavilionName, $startHour = null, $hours = null)
    {
        $timezone = '+05:00';
        $workEndHour = self::getWorkEndHour($pavilionName, $date);

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
                $start = $date . 'T' . sprintf('%02d', $startHour) . ':00:00' . $timezone;
                $endHour = $startHour + $hours;
                if ($endHour > $workEndHour) {
                    return null; // Нельзя бронировать после окончания работы
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
     * Получение доступных типов аренды с учетом ограничений
     */
    public static function getAvailableRentalTypes($pavilionName, $date)
    {
        $gazebo = self::getGazeboDataByName($pavilionName);
        if (!$gazebo) return [];

        $restrictions = self::getDateRestrictions($pavilionName, $date);

        $allTypes = [
            'hourly' => ['price' => $gazebo['hourly_price'], 'label' => 'Почасовая аренда', 'min_hours' => 4],
            'full_day' => ['price' => $gazebo['full_day_price'], 'label' => 'Весь день'],
            'night' => ['price' => $gazebo['night_price'], 'label' => 'Ночь (01:00-09:00)']
        ];

        // Фильтруем по ограничениям
        if ($restrictions['is_special'] && !empty($restrictions['allowed_types'])) {
            $allTypes = array_intersect_key($allTypes, array_flip($restrictions['allowed_types']));
        }

        // Удаляем типы с нулевой ценой
        foreach ($allTypes as $key => $type) {
            if ($type['price'] <= 0) {
                unset($allTypes[$key]);
            }
        }

        return $allTypes;
    }

    /**
     * Получение ограничений для даты
     */
    public static function getDateRestrictions($pavilionName, $date)
    {
        global $DB;

        $sql = "SELECT * FROM avs_booking_special_dates 
                WHERE PAVILION_ID = '" . $DB->ForSql($pavilionName) . "' 
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

        return [
            'is_special' => false,
            'restriction_type' => 'standard',
            'allowed_types' => ['hourly', 'full_day', 'night'],
            'price_modifier' => 1,
            'description' => ''
        ];
    }

    /**
     * Получение данных беседки по имени
     */
    public static function getGazeboDataByName($name)
    {
        if (!Loader::includeModule('iblock')) return null;

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
                'PROPERTY_DEPOSIT_AMOUNT'
            ]
        );

        if ($element = $res->Fetch()) {
            $deposit = (float)$element['PROPERTY_DEPOSIT_AMOUNT_VALUE'];
            if (!$deposit) {
                $deposit = self::getDefaultDeposit($element['NAME']);
            }

            return [
                'id' => (int)$element['ID'],
                'name' => (string)$element['NAME'],
                'resource_id' => (int)$element['PROPERTY_LIBREBOOKING_RESOURCE_ID_VALUE'],
                'hourly_price' => (float)$element['PROPERTY_PRICE_HOUR_VALUE'],
                'full_day_price' => (float)$element['PROPERTY_PRICE_VALUE'],
                'night_price' => (float)$element['PROPERTY_PRICE_NIGHT_VALUE'],
                'deposit_amount' => $deposit
            ];
        }
        return null;
    }

    /**
     * Создание заказа
     */
    public static function createOrder($data)
    {
        return \AVS\Booking\Order::create($data);
    }

    /**
     * Обновление заказа
     */
    public static function updateOrder($orderId, $data)
    {
        return \AVS\Booking\Order::update($orderId, $data);
    }

    /**
     * Получение заказа
     */
    public static function getOrder($orderId)
    {
        return \AVS\Booking\Order::get($orderId);
    }

    /**
     * Получение списка заказов
     */
    public static function getOrdersList($filter = [], $limit = 100)
    {
        return \AVS\Booking\Order::getList($filter, $limit, 0);
    }

    /**
     * Удаление заказа (мягкое)
     */
    public static function deleteOrder($orderId, $userId = null)
    {
        return \AVS\Booking\Order::softDelete($orderId, $userId);
    }
}
