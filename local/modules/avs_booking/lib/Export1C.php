<?php

use Bitrix\Main\Config\Option;

class AVSExport1C
{
    private $moduleId = 'avs_booking';
    private $apiUrl;
    private $apiKey;

    public function __construct()
    {
        $this->apiUrl = Option::get($this->moduleId, 'export_1c_url', '');
        $this->apiKey = Option::get($this->moduleId, 'export_1c_key', '');
    }

    public function sendBooking($bookingData, $reference)
    {
        if (!$this->apiUrl || !$this->apiKey) {
            $this->log('Ошибка: не настроен URL или API-ключ для 1С');
            return ['success' => false, 'message' => 'Не настроена интеграция с 1С'];
        }

        $data = [
            'api_key' => $this->apiKey,
            'action' => 'create_booking',
            'reference' => $reference,
            'booking' => [
                'resource_id' => $bookingData['resource_id'],
                'resource_name' => $bookingData['resource_name'],
                'date' => $bookingData['date'],
                'rental_type' => $bookingData['rental_type'],
                'start_time' => $bookingData['start_time'],
                'end_time' => $bookingData['end_time'],
                'total_price' => $bookingData['total_price'],
                'deposit_amount' => $bookingData['deposit_amount'] ?? 0
            ],
            'customer' => [
                'first_name' => $bookingData['user_data']['first_name'],
                'last_name' => $bookingData['user_data']['last_name'],
                'full_name' => trim($bookingData['user_data']['first_name'] . ' ' . $bookingData['user_data']['last_name']),
                'phone' => $bookingData['user_data']['phone'],
                'email' => $bookingData['user_data']['email'],
                'comment' => $bookingData['user_data']['comment'] ?? ''
            ],
            'created_at' => date('Y-m-d H:i:s')
        ];

        $ch = curl_init($this->apiUrl . '/booking/create');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->log("CURL ошибка: $error");
            return ['success' => false, 'message' => "Ошибка соединения: $error"];
        }

        if ($httpCode != 200) {
            $this->log("HTTP ошибка: $httpCode, ответ: $response");
            return ['success' => false, 'message' => "Ошибка 1С: HTTP $httpCode"];
        }

        $result = json_decode($response, true);

        if ($result && isset($result['success']) && $result['success']) {
            $this->log("Бронирование $reference отправлено, документ №{$result['document_number']}");
            return ['success' => true, 'message' => 'Отправлено в 1С', 'document_number' => $result['document_number']];
        } else {
            $errorMsg = $result['message'] ?? 'Неизвестная ошибка';
            $this->log("Ошибка 1С: $errorMsg");
            return ['success' => false, 'message' => "Ошибка 1С: $errorMsg"];
        }
    }

    public function importPricesFrom1C()
    {
        if (!$this->apiUrl || !$this->apiKey) {
            $this->log('Ошибка: не настроен URL или API-ключ для 1С');
            return ['success' => false, 'message' => 'Не настроена интеграция с 1С'];
        }

        $url = $this->apiUrl . '/get_prices';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Key: ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->log("CURL ошибка: $error");
            return ['success' => false, 'message' => "Ошибка соединения: $error"];
        }

        if ($httpCode != 200) {
            $this->log("HTTP ошибка: $httpCode, ответ: $response");
            return ['success' => false, 'message' => "Ошибка API 1С: HTTP $httpCode"];
        }

        $data = json_decode($response, true);

        if (!$data || !isset($data['success']) || !$data['success']) {
            $errorMsg = $data['message'] ?? 'Неизвестная ошибка';
            $this->log("Ошибка при получении цен: $errorMsg");
            return ['success' => false, 'message' => "Ошибка 1С: $errorMsg"];
        }

        $prices = $data['prices'] ?? [];
        if (empty($prices)) {
            $this->log("Нет данных о ценах");
            return ['success' => true, 'message' => 'Нет новых цен', 'updated' => 0];
        }

        $updated = $this->savePricesToIblock($prices);
        $this->log("Импортировано цен: $updated");

        return [
            'success' => true,
            'message' => "Импортировано цен: $updated",
            'updated' => $updated,
            'total' => count($prices)
        ];
    }

    private function savePricesToIblock($prices)
    {
        if (!\Bitrix\Main\Loader::includeModule('iblock')) {
            return 0;
        }

        $pricePeriodsIblockId = Option::get($this->moduleId, 'price_periods_iblock_id', 0);
        if (!$pricePeriodsIblockId) {
            return 0;
        }

        $updated = 0;

        foreach ($prices as $priceData) {
            $elementId = $this->findResourceElementId($priceData);
            if (!$elementId) {
                continue;
            }

            $this->deletePeriodByResourceAndDates($elementId, $priceData['date_from'], $priceData['date_to']);

            $el = new \CIBlockElement();
            $result = $el->Add([
                'IBLOCK_ID' => $pricePeriodsIblockId,
                'NAME' => "Период с {$priceData['date_from']} по {$priceData['date_to']}",
                'ACTIVE' => 'Y',
                'PROPERTY_VALUES' => [
                    'RESOURCE_ID' => $elementId,
                    'DATE_FROM' => $priceData['date_from'],
                    'DATE_TO' => $priceData['date_to'],
                    'PRICE_HOUR' => $priceData['price_hour'],
                    'PRICE_DAY' => $priceData['price_day'],
                    'PRICE_NIGHT' => $priceData['price_night']
                ]
            ]);

            if ($result) {
                $updated++;
                \CIBlockElement::SetPropertyValues($elementId, 12, $priceData['price_hour'], 'PRICE_HOUR');
                \CIBlockElement::SetPropertyValues($elementId, 12, $priceData['price_day'], 'PRICE');
                \CIBlockElement::SetPropertyValues($elementId, 12, $priceData['price_night'], 'PRICE_NIGHT');
            }
        }

        return $updated;
    }

    private function findResourceElementId($priceData)
    {
        if (!empty($priceData['resource_id'])) {
            $res = \CIBlockElement::GetList(
                [],
                [
                    'IBLOCK_ID' => 12,
                    'PROPERTY_LIBREBOOKING_RESOURCE_ID' => $priceData['resource_id'],
                    'ACTIVE' => 'Y'
                ],
                false,
                false,
                ['ID']
            );
            if ($el = $res->Fetch()) {
                return $el['ID'];
            }
        }

        if (!empty($priceData['resource_name'])) {
            $res = \CIBlockElement::GetList(
                [],
                [
                    'IBLOCK_ID' => 12,
                    'NAME' => $priceData['resource_name'],
                    'ACTIVE' => 'Y'
                ],
                false,
                false,
                ['ID']
            );
            if ($el = $res->Fetch()) {
                return $el['ID'];
            }
        }

        return null;
    }

    private function deletePeriodByResourceAndDates($elementId, $dateFrom, $dateTo)
    {
        $pricePeriodsIblockId = Option::get($this->moduleId, 'price_periods_iblock_id', 0);

        $res = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $pricePeriodsIblockId,
                'PROPERTY_RESOURCE_ID' => $elementId,
                'PROPERTY_DATE_FROM' => $dateFrom,
                'PROPERTY_DATE_TO' => $dateTo
            ],
            false,
            false,
            ['ID']
        );

        while ($period = $res->Fetch()) {
            \CIBlockElement::Delete($period['ID']);
        }
    }

    private function log($message)
    {
        $logFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/export_1c.log';
        $logMessage = date('[Y-m-d H:i:s]') . ' ' . $message . PHP_EOL;
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}
