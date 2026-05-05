<?php

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

if ($APPLICATION->GetGroupRight('avs_booking') >= 'R') {
    $aMenu = [
        'parent_menu' => 'global_menu_services',
        'sort' => 100,
        'text' => 'AVS Booking',
        'title' => 'Управление бронированиями',
        'icon' => 'sys_menu_icon',
        'page_icon' => 'sys_page_icon',
        'items_id' => 'menu_avs_booking',
        'items' => [
            [
                'text' => 'Список бронирований',
                'url' => 'avs_booking_orders.php?lang=' . LANGUAGE_ID,
                'more_url' => ['avs_booking_orders.php'],
                'title' => 'Просмотр и управление бронированиями'
            ],
            [
                'text' => 'Настройки',
                'url' => '/bitrix/admin/settings.php?mid=avs_booking&lang=' . LANGUAGE_ID,
                'more_url' => ['settings.php'],
                'title' => 'Настройки модуля'
            ]
        ]
    ];

    return $aMenu;
}

return false;
