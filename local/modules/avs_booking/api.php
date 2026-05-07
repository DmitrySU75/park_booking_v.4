<?php

/**
 * Файл: /local/modules/avs_booking/api.php
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use AVS\Booking\Api;

CModule::IncludeModule('avs_booking');

$api = new Api();
$api->handleRequest();

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
