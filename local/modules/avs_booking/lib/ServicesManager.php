<?php

class AVSServicesManager
{
    private $services = [];

    public function registerService($code, $name, $callback)
    {
        $this->services[$code] = [
            'name' => $name,
            'callback' => $callback
        ];
    }

    public function getServices()
    {
        return $this->services;
    }

    public function executeService($code, $params)
    {
        if (!isset($this->services[$code])) {
            throw new Exception("Service {$code} not found");
        }

        return call_user_func($this->services[$code]['callback'], $params);
    }

    public function getAvailableTimes($resourceId, $date, $durationHours)
    {
        $api = AVSBookingModule::getApiClient();
        $slots = $api->getAvailableSlotsForDate($resourceId, $date);

        $availableTimes = [];
        foreach ($slots as $slot) {
            $startHour = (int)substr($slot['start'], 11, 2);
            $availableTimes[] = [
                'hour' => $startHour,
                'label' => $startHour . ':00',
                'value' => $startHour
            ];
        }

        return $availableTimes;
    }
}
