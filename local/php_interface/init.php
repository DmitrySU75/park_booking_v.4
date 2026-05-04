<?php
date_default_timezone_set('Asia/Yekaterinburg');

require_once __DIR__ . '/librebooking_config.php';
require_once __DIR__ . '/lib/LibreBookingAPI.php';
require_once __DIR__ . '/include/smartfilter_date.php';

use Bitrix\Main\EventManager;

CModule::AddAutoloadClasses(
    'avs_booking',
    [
        'AVSBookingModule' => '/local/modules/avs_booking/include.php',
        'AVSBookingApiClient' => '/local/modules/avs_booking/lib/ApiClient.php',
        'AVSPaymentHandler' => '/local/modules/avs_booking/lib/PaymentHandler.php',
        'AVSNotificationService' => '/local/modules/avs_booking/lib/NotificationService.php',
        'AVSServicesManager' => '/local/modules/avs_booking/lib/ServicesManager.php',
    ]
);

$eventManager = EventManager::getInstance();

$eventManager->addEventHandler(
    'iblock',
    'OnBuildFilterSelect',
    ['LibreBookingSmartFilter', 'onBuildFilterSelect']
);

$eventManager->addEventHandler(
    'iblock',
    'OnBeforeCIBlockElementGetList',
    ['LibreBookingSmartFilter', 'onBeforeGetList']
);
