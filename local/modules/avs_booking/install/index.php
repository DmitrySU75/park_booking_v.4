<?php

use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\Loc;

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

    private $events = [
        ['sale', 'OnSalePaymentPaid', 'avs_booking', 'AVSBookingHandlers', 'onSalePaymentPaid'],
    ];

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('AVS_BOOKING_MODULE_NAME') ?: 'Модуль бронирования AVS';
        $this->MODULE_DESCRIPTION = Loc::getMessage('AVS_BOOKING_MODULE_DESC') ?: 'Интеграция с LibreBooking, ЮKassa, Битрикс24, 1С';
        $this->PARTNER_NAME = Loc::getMessage('AVS_BOOKING_PARTNER_NAME') ?: 'AVS Group';
        $this->PARTNER_URI = 'https://avsgroup.ru';
    }

    public function DoInstall()
    {
        global $APPLICATION;

        // Проверка версии главного модуля
        if (!CheckVersion(ModuleManager::getVersion('main'), '14.00.00')) {
            $APPLICATION->ThrowException(Loc::getMessage('AVS_BOOKING_INSTALL_ERROR_VERSION') ?: 'Версия главного модуля ниже 14.00.00');
            return false;
        }

        // Проверка необходимых расширений PHP
        $requiredExtensions = ['curl', 'json', 'mbstring', 'pdo_mysql'];
        $missingExtensions = [];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $missingExtensions[] = $ext;
            }
        }
        if (!empty($missingExtensions)) {
            $APPLICATION->ThrowException('Отсутствуют необходимые расширения PHP: ' . implode(', ', $missingExtensions));
            return false;
        }

        // Проверка модулей Битрикс
        if (!Loader::includeModule('iblock')) {
            $APPLICATION->ThrowException('Модуль "Инфоблоки" не установлен');
            return false;
        }

        ModuleManager::registerModule($this->MODULE_ID);

        $this->InstallFiles();
        $this->InstallDB();
        $this->InstallEvents();

        // Вывод сообщения об успешной установке
        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('AVS_BOOKING_INSTALL_TITLE') ?: 'Установка модуля бронирования AVS',
            __DIR__ . '/step.php'
        );

        return true;
    }

    public function DoUninstall()
    {
        global $APPLICATION;

        $context = \Bitrix\Main\Application::getInstance()->getContext();
        $request = $context->getRequest();
        $step = (int)$request->get('step');

        if ($step < 2) {
            $APPLICATION->IncludeAdminFile(
                Loc::getMessage('AVS_BOOKING_UNINSTALL_TITLE') ?: 'Удаление модуля бронирования AVS',
                __DIR__ . '/unstep.php'
            );
            return false;
        }

        $preserveData = ($request->get('preserve_data') === 'Y');

        if (!$preserveData) {
            $this->UnInstallDB();
        }

        $this->UnInstallFiles();
        $this->UnInstallEvents();

        ModuleManager::unRegisterModule($this->MODULE_ID);

        return true;
    }

    public function InstallFiles()
    {
        // 1. Копируем admin-файлы в /bitrix/admin/
        $adminSource = __DIR__ . '/../admin';
        $adminTarget = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin';
        if (is_dir($adminSource)) {
            CopyDirFiles($adminSource, $adminTarget, true, true);
        }

        // 2. Создаём компонент
        $componentDir = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/components/avs_booking/booking.form';
        if (!is_dir($componentDir)) {
            mkdir($componentDir, 0755, true);
        }

        // 2.1. Файл .description.php
        $desc = '<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentDescription = [
    "NAME" => "Форма бронирования",
    "DESCRIPTION" => "Форма бронирования беседок",
    "PATH" => [
        "ID" => "avs_booking",
        "NAME" => "AVS Бронирование",
    ],
];
';
        file_put_contents($componentDir . '/.description.php', $desc);

        // 2.2. Файл component.php
        $comp = '<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

if (!CModule::IncludeModule("avs_booking")) {
    ShowError("Модуль avs_booking не установлен");
    return;
}

$elementId = intval($arParams["ELEMENT_ID"]);
if (!$elementId) {
    ShowError("Не указан ID беседки");
    return;
}

$arResult["GAZEBO_DATA"] = AVSBookingModule::getGazeboData($elementId);
$arResult["ELEMENT_ID"] = $elementId;

$this->IncludeComponentTemplate();
';
        file_put_contents($componentDir . '/component.php', $comp);

        // 2.3. Папка шаблона
        $templateDir = $componentDir . '/templates/.default';
        if (!is_dir($templateDir)) {
            mkdir($templateDir, 0755, true);
        }

        // 2.4. Копируем template.php из модуля
        $sourceTemplate = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/avs_booking/templates/.default/template.php';
        if (file_exists($sourceTemplate)) {
            copy($sourceTemplate, $templateDir . '/template.php');
        }

        return true;
    }

    public function UnInstallFiles()
    {
        // Удаляем admin-файлы
        DeleteDirFilesEx('/bitrix/admin/avs_booking_price_periods.php');

        // Удаляем компонент
        DeleteDirFilesEx('/bitrix/components/avs_booking/booking.form');

        return true;
    }

    public function InstallEvents()
    {
        $eventManager = EventManager::getInstance();

        foreach ($this->events as $event) {
            $eventManager->registerEventHandler(
                $event[0],
                $event[1],
                $event[2],
                $event[3],
                $event[4]
            );
        }

        return true;
    }

    public function UnInstallEvents()
    {
        $eventManager = EventManager::getInstance();

        foreach ($this->events as $event) {
            $eventManager->unRegisterEventHandler(
                $event[0],
                $event[1],
                $event[2],
                $event[3],
                $event[4]
            );
        }

        return true;
    }

    public function InstallDB()
    {
        // Получаем текущий домен
        $domain = $_SERVER['HTTP_HOST'] ?? 'park.na4u.ru';

        // API LibreBooking
        Option::set($this->MODULE_ID, 'api_url', 'https://' . $domain . '/booking/Web/Services/index.php');
        Option::set($this->MODULE_ID, 'api_username', '');
        Option::set($this->MODULE_ID, 'api_password', '');
        Option::set($this->MODULE_ID, 'default_schedule_id', 2);
        Option::set($this->MODULE_ID, 'timezone_offset', '+05:00');

        // Оплата
        Option::set($this->MODULE_ID, 'default_deposit_amount', 0);
        Option::set($this->MODULE_ID, 'service_product_id', 0);
        Option::set($this->MODULE_ID, 'yookassa_paysystem_id', 2);
        Option::set($this->MODULE_ID, 'yookassa_shop_id', '');
        Option::set($this->MODULE_ID, 'yookassa_secret_key', '');

        // Уведомления
        Option::set($this->MODULE_ID, 'admin_email', '');
        Option::set($this->MODULE_ID, 'bitrix24_webhook', '');

        // API для 1С
        Option::set($this->MODULE_ID, 'api_1c_key', md5(uniqid('avs_booking_', true) . time()));

        // Экспорт в 1С
        Option::set($this->MODULE_ID, 'export_1c_url', '');
        Option::set($this->MODULE_ID, 'export_1c_key', '');

        // Сезонные настройки
        Option::set($this->MODULE_ID, 'summer_season_start', '01.06');
        Option::set($this->MODULE_ID, 'summer_season_end', '31.08');
        Option::set($this->MODULE_ID, 'winter_end_hour', '22');
        Option::set($this->MODULE_ID, 'summer_end_hour', '23');

        // Создаём инфоблоки
        $this->createIblocks();

        return true;
    }

    public function UnInstallDB()
    {
        // Удаляем настройки модуля
        Option::delete($this->MODULE_ID);

        // Удаляем инфоблоки (опционально)
        $this->removeIblocks();

        return true;
    }

    private function createIblocks()
    {
        if (!Loader::includeModule('iblock')) {
            return false;
        }

        $iblock = new \CIBlock();

        // 1. Инфоблок для услуг и скидок
        $servicesIblockId = $iblock->Add([
            'ACTIVE' => 'Y',
            'NAME' => 'Услуги и скидки бронирования',
            'IBLOCK_TYPE_ID' => 'services',
            'CODE' => 'booking_services',
            'SITE_ID' => ['s1'],
            'GROUP_ID' => ['2' => 'R', '1' => 'W'],
            'VERSION' => 2
        ]);

        if ($servicesIblockId) {
            $this->addServiceProperties($servicesIblockId);
            Option::set($this->MODULE_ID, 'services_iblock_id', $servicesIblockId);
        }

        // 2. Инфоблок для ценовых периодов
        $priceIblockId = $iblock->Add([
            'ACTIVE' => 'Y',
            'NAME' => 'Ценовые периоды',
            'IBLOCK_TYPE_ID' => 'prices',
            'CODE' => 'price_periods',
            'SITE_ID' => ['s1'],
            'GROUP_ID' => ['2' => 'R', '1' => 'W'],
            'VERSION' => 2
        ]);

        if ($priceIblockId) {
            $this->addPricePeriodProperties($priceIblockId);
            Option::set($this->MODULE_ID, 'price_periods_iblock_id', $priceIblockId);
        }

        return true;
    }

    private function addServiceProperties($iblockId)
    {
        $properties = [
            'SERVICE_TYPE' => [
                'NAME' => 'Тип услуги',
                'PROPERTY_TYPE' => 'L',
                'VALUES' => ['extra' => 'Доп. услуга', 'discount' => 'Скидка']
            ],
            'PRICE_TYPE' => [
                'NAME' => 'Тип цены',
                'PROPERTY_TYPE' => 'L',
                'VALUES' => [
                    'one_time' => 'Единоразовая',
                    'per_day' => 'В сутки',
                    'per_person' => 'За человека',
                    'discount_percent' => 'Скидка %'
                ]
            ],
            'PRICE_VALUE' => ['NAME' => 'Стоимость/процент', 'PROPERTY_TYPE' => 'N'],
            'APPLY_TO_RESOURCES' => ['NAME' => 'ID ресурсов', 'PROPERTY_TYPE' => 'S', 'MULTIPLE' => 'Y'],
            'DATE_FROM' => ['NAME' => 'Дата начала', 'PROPERTY_TYPE' => 'S', 'USER_TYPE' => 'DateTime'],
            'DATE_TO' => ['NAME' => 'Дата окончания', 'PROPERTY_TYPE' => 'S', 'USER_TYPE' => 'DateTime']
        ];

        foreach ($properties as $code => $prop) {
            $prop['IBLOCK_ID'] = $iblockId;
            $prop['CODE'] = $code;
            $iblockProp = new \CIBlockProperty();
            $iblockProp->Add($prop);
        }
    }

    private function addPricePeriodProperties($iblockId)
    {
        $properties = [
            'RESOURCE_ID' => ['NAME' => 'Беседка', 'PROPERTY_TYPE' => 'S', 'IS_REQUIRED' => 'Y'],
            'DATE_FROM' => ['NAME' => 'Дата начала', 'PROPERTY_TYPE' => 'S', 'USER_TYPE' => 'DateTime', 'IS_REQUIRED' => 'Y'],
            'DATE_TO' => ['NAME' => 'Дата окончания', 'PROPERTY_TYPE' => 'S', 'USER_TYPE' => 'DateTime', 'IS_REQUIRED' => 'Y'],
            'PRICE_HOUR' => ['NAME' => 'Цена час', 'PROPERTY_TYPE' => 'N'],
            'PRICE_DAY' => ['NAME' => 'Цена день', 'PROPERTY_TYPE' => 'N'],
            'PRICE_NIGHT' => ['NAME' => 'Цена ночь', 'PROPERTY_TYPE' => 'N']
        ];

        foreach ($properties as $code => $prop) {
            $prop['IBLOCK_ID'] = $iblockId;
            $prop['CODE'] = $code;
            $iblockProp = new \CIBlockProperty();
            $iblockProp->Add($prop);
        }
    }

    private function removeIblocks()
    {
        $servicesIblockId = Option::get($this->MODULE_ID, 'services_iblock_id', 0);
        if ($servicesIblockId) {
            \CIBlock::Delete($servicesIblockId);
        }

        $priceIblockId = Option::get($this->MODULE_ID, 'price_periods_iblock_id', 0);
        if ($priceIblockId) {
            \CIBlock::Delete($priceIblockId);
        }
    }
}
