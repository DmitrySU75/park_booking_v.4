<?php

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

if ($APPLICATION->GetGroupRight('avs_booking') >= 'R') {
    $aMenu = [
        'parent_menu' => 'global_menu_services',
        'sort' => 100,
        'text' => 'AVS Booking',
        'title' => 'Система бронирования',
        'icon' => 'sys_menu_icon',
        'items_id' => 'menu_avs_booking',
        'items' => [
            [
                'text' => 'Дашборд',
                'url' => 'avs_booking_dashboard.php?lang=' . LANGUAGE_ID,
                'title' => 'Статистика и обзор'
            ],
            [
                'text' => 'Бронирования',
                'url' => 'avs_booking_orders.php?lang=' . LANGUAGE_ID,
                'title' => 'Управление бронированиями'
            ],
            [
                'text' => 'Особые даты',
                'url' => 'avs_booking_special_dates.php?lang=' . LANGUAGE_ID,
                'title' => 'Ограничения по датам'
            ],
            [
                'text' => 'Скидки и промокоды',
                'url' => 'avs_booking_discounts.php?lang=' . LANGUAGE_ID,
                'title' => 'Управление скидками'
            ],
            [
                'text' => 'Настройки',
                'url' => '/bitrix/admin/settings.php?mid=avs_booking&lang=' . LANGUAGE_ID,
                'title' => 'Настройки модуля'
            ]
        ]
    ];

    return $aMenu;
}

return false;
