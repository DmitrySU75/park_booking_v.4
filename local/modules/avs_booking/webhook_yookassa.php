<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use AVS\Booking\Payment;

CModule::IncludeModule('avs_booking');

$allowedIps = ['185.71.76.0/27', '185.71.77.0/27', '77.75.153.0/25', '77.75.154.0/25', '77.75.156.0/25', '77.75.157.0/25'];
$clientIp = $_SERVER['REMOTE_ADDR'];

$ipValid = false;
foreach ($allowedIps as $ipRange) {
    if (ipInRange($clientIp, $ipRange)) {
        $ipValid = true;
        break;
    }
}

if (!$ipValid) {
    http_response_code(403);
    die('Forbidden');
}

Payment::handleWebhook();

echo 'OK';

function ipInRange($ip, $range)
{
    if (strpos($range, '/') === false) {
        return $ip === $range;
    }

    list($range, $netmask) = explode('/', $range, 2);
    $rangeDecimal = ip2long($range);
    $ipDecimal = ip2long($ip);
    $wildcardDecimal = pow(2, (32 - $netmask)) - 1;
    $netmaskDecimal = ~$wildcardDecimal;

    return ($ipDecimal & $netmaskDecimal) == ($rangeDecimal & $netmaskDecimal);
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
