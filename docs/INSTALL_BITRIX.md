# Инструкция по установке модуля бронирования AVS

## Системные требования

| Компонент | Минимальная версия | Рекомендуемая версия |
|-----------|-------------------|---------------------|
| PHP | 7.4 | 8.2+ |
| Битрикс | 20.0.0 | Последняя |
| MySQL | 5.6 | 8.0+ |
| LibreBooking | 2.8 | Последняя |

### Необходимые расширения PHP

- curl
- json
- mbstring
- date
- openssl
- pdo_mysql

## 1. Подготовка LibreBooking

### 1.1. Установка LibreBooking

```bash
cd /путь/до/сайта
git clone https://github.com/LibreBooking/librebooking.git booking
cd booking
composer install --no-dev
cp config/config.dist.php config/config.php
```
### 1.2. Создание базы данных

```sql
CREATE DATABASE librebooking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'libreuser'@'localhost' IDENTIFIED BY 'ваш_пароль';
GRANT ALL PRIVILEGES ON librebooking.* TO 'libreuser'@'localhost';
FLUSH PRIVILEGES;
```

### 1.3 Настройка config.php

Отредактируйте config/config.php:

```php
return [
    'settings' => [
        'database' => [
            'type' => 'mysql',
            'user' => 'libreuser',
            'password' => 'ваш_пароль',
            'hostspec' => 'localhost',
            'name' => 'librebooking',
        ],
        'script.url' => 'https://ваш-домен.ru/booking',
        'default.timezone' => 'Asia/Yekaterinburg',
        'default.language' => 'ru_RU',
    ]
];
```

### 1.4. Создание расписаний

Войдите в LibreBooking как администратор
Application Management → Schedules
Создайте «Почасовое бронирование» (слоты 09:00-23:00, интервал 1 час)
Создайте «Аренда на весь день» (слот 10:00-22:00)
Создайте «Ночная аренда» (слот 22:00-09:00, разрешить несколько дней)
