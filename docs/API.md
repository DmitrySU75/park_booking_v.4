
# API документация модуля avs_booking

## Базовый URL
https://ваш-домен.ru/local/modules/avs_booking/api_1c.php

## Аутентификация

Для всех запросов требуется API-ключ в заголовке:
X-API-Key: ваш_секретный_ключ

## Эндпоинты

### 1. Создание бронирования

**Метод:** POST

**Параметры:**


| Поле | Тип | Обязательное | Описание |
|------|-----|--------------|----------|
| action | string | Да | `create_booking` |
| resource_id | int | Да | ID ресурса в LibreBooking |
| start_time | string | Да | Время начала (ISO 8601) |
| end_time | string | Да | Время окончания (ISO 8601) |
| first_name | string | Да | Имя клиента |
| last_name | string | Да | Фамилия клиента |
| phone | string | Нет | Телефон |
| email | string | Нет | Email |
| comment | string | Нет | Комментарий |
| deposit_amount | float | Нет | Сумма предоплаты |

**Пример запроса:**

```json
{
    "action": "create_booking",
    "resource_id": 116,
    "start_time": "2026-05-20T14:00:00+05:00",
    "end_time": "2026-05-20T18:00:00+05:00",
    "first_name": "Иван",
    "last_name": "Петров",
    "phone": "+7 900 123-45-67",
    "email": "ivan@example.com"
}
```
Пример ответа:

```json
{
    "success": true,
    "reference": "REF123456",
    "message": "Booking created successfully"
}
```
### 2. Отмена бронирования

Метод: POST

Параметры:

| Поле | Тип | Обязательное | Описание |
|------|-----|--------------|----------|
| action | string | Да | cancel_booking |
| reference | string | Да | Номер бронирования |

Пример запроса:

```json
{
    "action": "cancel_booking",
    "reference": "REF123456"
}
```
Пример ответа:

```json
{
    "success": true,
    "message": "Booking cancelled successfully"
}
```
### 3. Изменение бронирования

Метод: POST

Параметры:

| Поле | Тип | Обязательное | Описание |
|------|-----|--------------|----------|
|action |	string |	Да |	change_booking|
|reference |	string |	Да |	Номер бронирования|
|new_resource_id |	int |	Нет |	Новый ID ресурса|
|new_start_time |	string |	Да |	Новое время начала|
|new_end_time |	string |	Да |	Новое время окончания|

Пример запроса:

```json
{
    "action": "change_booking",
    "reference": "REF123456",
    "new_start_time": "2026-05-21T15:00:00+05:00",
    "new_end_time": "2026-05-21T19:00:00+05:00"
}
```
Пример ответа:

```json
{
    "success": true,
    "reference": "REF123456",
    "message": "Booking updated successfully"
}
```

### 4. Обновление цен

Метод: POST

Параметры:

| Поле | Тип | Обязательное | Описание |
|------|-----|--------------|----------|
|action |	string |	Да |	update_prices |
|prices |	array |	Да |	Массив ценовых периодов |

Пример запроса:

```json
{
    "action": "update_prices",
    "prices": [
        {
            "resource_id": 116,
            "resource_name": "Беседка №38",
            "date_from": "2026-06-01",
            "date_to": "2026-08-31",
            "price_hour": 950,
            "price_day": 8900,
            "price_night": 3800
        }
    ]
}
```
Пример ответа:

```json
{
    "success": true,
    "updated": 1,
    "message": "Обновлено цен: 1"
}
```

### 5. Получение статуса бронирования

Метод: POST

Параметры:

| Поле | Тип | Обязательное | Описание |
|------|-----|--------------|----------|
|action |	string |	Да |	get_booking_status|
|reference |	string |	Да |	Номер бронирования|

Пример запроса:

```json
{
    "action": "get_booking_status",
    "reference": "REF123456"
}
```
Пример ответа:

```json
{
    "success": true,
    "reference": "REF123456",
    "status": "active",
    "start_time": "2026-05-20T14:00:00+05:00",
    "end_time": "2026-05-20T18:00:00+05:00"
}
```
Коды ошибок

|HTTP код	| Описание |
|------|-----------|
|200 |	Успешно |
|400 |	Неверные параметры |
|401 |	Неверный API-ключ |
|404 |	Ресурс не найден |
|405 |	Метод не поддерживается |
|409 |	Конфликт (время уже занято) |
|500 |	Внутренняя ошибка сервера |

Логирование
Все запросы логируются в файл:

```text
/upload/api_1c_debug.log
```
