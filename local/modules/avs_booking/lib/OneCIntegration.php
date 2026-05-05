<?php

namespace AVS\Booking;

class OneCIntegration
{
    public function exportOrders($orders)
    {
        $export = new \AVSExport1C();
        return ['success' => $export->exportOrders($orders)];
    }

    public function syncPrices($prices)
    {
        // Синхронизация цен из 1С
        foreach ($prices as $priceData) {
            // Обновление цен в инфоблоке
        }

        return ['success' => true, 'synced' => count($prices)];
    }
}
