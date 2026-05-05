<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\EventManager;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;

Loc::loadMessages(__FILE__);

class avs_booking extends CModule
{
    public $MODULE_ID = 'avs_booking';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    public function __construct()
    {
        $arModuleVersion = [];
        include(__DIR__ . '/version.php');

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = 'AVS Booking System';
        $this->MODULE_DESCRIPTION = 'Модуль бронирования беседок с интеграцией LibreBooking, ЮKassa, 1С и Битрикс24';
        $this->PARTNER_NAME = 'AVS Group';
        $this->PARTNER_URI = 'https://avsgroup.ru';
    }

    public function DoInstall()
    {
        global $DB, $APPLICATION;

        if (!CheckVersion(ModuleManager::getVersion('main'), '20.0.0')) {
            $APPLICATION->ThrowException('Требуется версия главного модуля не ниже 20.0.0');
            return false;
        }

        $this->InstallDB();
        $this->InstallEvents();
        $this->InstallFiles();
        $this->InstallOptions();

        ModuleManager::registerModule($this->MODULE_ID);

        return true;
    }

    public function DoUninstall()
    {
        global $DB, $APPLICATION;

        $context = \Bitrix\Main\Application::getInstance()->getContext();
        $request = $context->getRequest();

        if ($request['savedata'] == 'Y') {
            // Сохраняем данные
        } else {
            $this->UnInstallDB();
        }

        $this->UnInstallEvents();
        $this->UnInstallFiles();
        $this->UnInstallOptions();

        ModuleManager::unRegisterModule($this->MODULE_ID);

        return true;
    }

    public function InstallDB()
    {
        global $DB;

        $errors = $DB->RunSQLBatch(__DIR__ . '/db/install.sql');

        if (!empty($errors)) {
            return $errors;
        }

        // Регистрируем почтовые события
        $eventTypes = [
            'AVS_BOOKING_PAYMENT_SUCCESS' => [
                'LID' => 'ru',
                'EVENT_TYPE' => 'email',
                'NAME' => 'Успешная оплата бронирования',
                'DESCRIPTION' => '#ORDER_NUMBER# - Номер заказа
#CLIENT_NAME# - Имя клиента
#PAVILION_NAME# - Название беседки
#AMOUNT# - Сумма оплаты
#START_TIME# - Время начала
#END_TIME# - Время окончания'
            ],
            'AVS_BOOKING_ORDER_CREATED' => [
                'LID' => 'ru',
                'EVENT_TYPE' => 'email',
                'NAME' => 'Новое бронирование',
                'DESCRIPTION' => '#ORDER_NUMBER# - Номер заказа
#CLIENT_NAME# - Имя клиента
#CLIENT_PHONE# - Телефон клиента
#PAVILION_NAME# - Название беседки
#START_TIME# - Время начала
#END_TIME# - Время окончания
#PRICE# - Стоимость'
            ],
            'AVS_BOOKING_TIME_EXTENDED' => [
                'LID' => 'ru',
                'EVENT_TYPE' => 'email',
                'NAME' => 'Продление времени бронирования',
                'DESCRIPTION' => '#ORDER_NUMBER# - Номер заказа
#CLIENT_NAME# - Имя клиента
#PAVILION_NAME# - Название беседки
#OLD_END_TIME# - Старое время окончания
#NEW_END_TIME# - Новое время окончания
#ADDITIONAL_PRICE# - Доплата'
            ]
        ];

        foreach ($eventTypes as $eventName => $eventData) {
            \CEventType::Add([
                'LID' => $eventData['LID'],
                'EVENT_NAME' => $eventName,
                'NAME' => $eventData['NAME'],
                'DESCRIPTION' => $eventData['DESCRIPTION']
            ]);
        }

        return true;
    }

    public function UnInstallDB()
    {
        global $DB;

        $DB->RunSQLBatch(__DIR__ . '/db/uninstall.sql');

        // Удаляем почтовые события
        $eventTypes = [
            'AVS_BOOKING_PAYMENT_SUCCESS',
            'AVS_BOOKING_ORDER_CREATED',
            'AVS_BOOKING_TIME_EXTENDED'
        ];

        foreach ($eventTypes as $eventName) {
            \CEventType::Delete($eventName);
        }

        return true;
    }

    public function InstallEvents()
    {
        $eventManager = EventManager::getInstance();

        $eventManager->registerEventHandler(
            'avs_booking',
            'OnAfterOrderCreate',
            'avs_booking',
            'AVSBookingEventHandlers',
            'onAfterOrderCreate'
        );

        $eventManager->registerEventHandler(
            'avs_booking',
            'OnAfterOrderUpdate',
            'avs_booking',
            'AVSBookingEventHandlers',
            'onAfterOrderUpdate'
        );

        return true;
    }

    public function UnInstallEvents()
    {
        $eventManager = EventManager::getInstance();

        $eventManager->unRegisterEventHandler(
            'avs_booking',
            'OnAfterOrderCreate',
            'avs_booking',
            'AVSBookingEventHandlers',
            'onAfterOrderCreate'
        );

        $eventManager->unRegisterEventHandler(
            'avs_booking',
            'OnAfterOrderUpdate',
            'avs_booking',
            'AVSBookingEventHandlers',
            'onAfterOrderUpdate'
        );

        return true;
    }

    public function InstallFiles()
    {
        CopyDirFiles(
            $_SERVER['DOCUMENT_ROOT'] . '/local/modules/avs_booking/admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin',
            true,
            true
        );

        CopyDirFiles(
            $_SERVER['DOCUMENT_ROOT'] . '/local/modules/avs_booking/bitrix/components',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/components',
            true,
            true
        );

        return true;
    }

    public function UnInstallFiles()
    {
        DeleteDirFiles(
            $_SERVER['DOCUMENT_ROOT'] . '/local/modules/avs_booking/admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin'
        );

        return true;
    }

    public function InstallOptions()
    {
        Option::set($this->MODULE_ID, 'api_url', '');
        Option::set($this->MODULE_ID, 'api_username', '');
        Option::set($this->MODULE_ID, 'api_password', '');
        Option::set($this->MODULE_ID, 'api_key', '');
        Option::set($this->MODULE_ID, 'api_allowed_ips', '');
        Option::set($this->MODULE_ID, 'beton_systems_shop_id', '');
        Option::set($this->MODULE_ID, 'beton_systems_secret_key', '');
        Option::set($this->MODULE_ID, 'park_victory_shop_id', '');
        Option::set($this->MODULE_ID, 'park_victory_secret_key', '');
        Option::set($this->MODULE_ID, 'b24_webhook_url', '');
        Option::set($this->MODULE_ID, 'admin_email', '');
        Option::set($this->MODULE_ID, 'summer_season_start', '01.06');
        Option::set($this->MODULE_ID, 'summer_season_end', '31.08');
        Option::set($this->MODULE_ID, 'summer_end_hour', 23);
        Option::set($this->MODULE_ID, 'winter_end_hour', 22);
        Option::set($this->MODULE_ID, 'price_periods_iblock_id', 0);

        return true;
    }

    public function UnInstallOptions()
    {
        Option::delete($this->MODULE_ID);
        return true;
    }
}

class AVSBookingEventHandlers
{
    public static function onAfterOrderCreate($orderId, $data)
    {
        // Отправка уведомлений
        $notificationService = new AVSNotificationService();
        $order = \AVS\Booking\Order::get($orderId);

        if ($order) {
            $notificationService->sendAdminEmail($order['ORDER_NUMBER'], $order, $order['PRICE']);
            $notificationService->sendBitrix24Lead($order['ORDER_NUMBER'], $order, $order['PRICE']);
        }

        // Экспорт в 1С
        $export1C = new AVSExport1C();
        $export1C->exportOrder($orderId);
    }

    public static function onAfterOrderUpdate($orderId, $data)
    {
        if (isset($data['status']) && $data['status'] == 'paid') {
            $order = \AVS\Booking\Order::get($orderId);
            if ($order) {
                $notificationService = new AVSNotificationService();
                $notificationService->sendClientPaymentNotification($order);
            }
        }

        if (isset($data['extended_end_time'])) {
            $order = \AVS\Booking\Order::get($orderId);
            if ($order) {
                $notificationService = new AVSNotificationService();
                $notificationService->sendTimeExtensionNotification($order);
            }
        }

        // Экспорт в 1С при изменении статуса
        $export1C = new AVSExport1C();
        $export1C->exportOrder($orderId);
    }
}
