<?php

/**
 * Файл: /local/modules/avs_booking/ajax_sync.php
 * Быстрый эндпоинт для синхронизации
 * v.4.5.0
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use AVS\Booking\SyncManager;

header('Content-Type: application/json');

if (!check_bitrix_sessid()) {
    echo json_encode(['success' => false, 'error' => 'CSRF token mismatch']);
    exit;
}

global $USER;
if (!$USER->IsAdmin()) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

try {
    $sync = new SyncManager();
    
    switch ($action) {
        case 'quick':
            $result = $sync->quickSync();
            break;
        case 'full':
            $result = $sync->fullSync(30);
            break;
        case 'reservation':
            $reference = $_REQUEST['reference'] ?? '';
            if (!$reference) {
                echo json_encode(['success' => false, 'error' => 'Reference required']);
                exit;
            }
            $result = $sync->syncReservation($reference);
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
            exit;
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
