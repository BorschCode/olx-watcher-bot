# OLX Categories Monitoring Telegram Bot

Telegram-бот для автоматичного моніторингу рубрик OLX і сповіщення про нові оголошення за заданими параметрами.

Підтримує фоновий скрейпінг, фільтрацію результатів і керування підписками через адмін-панель.

---

## Features

* моніторинг рубрик OLX за URL
* фільтрація нових оголошень
* Telegram-сповіщення в реальному часі
* плановий запуск через scheduler / queue
* адмін-панель для керування джерелами
* Docker-оточення для швидкого запуску
* Redis-ready для черг

---

## Compliance with OLX Terms of Service

Скрапер отримує лише **публічні сторінки результатів пошуку**, які доступні звичайному браузеру.

Використовуються затримки між запитами:

* 2 секунди між сторінками
* 1 секунда між оголошеннями

`robots.txt` OLX не блокує search result сторінки:

```
Allow: /
```

Заборонені лише:

```
/api/
/admin/
/kontakt/
/druk/
```

---

## Tech Stack

* Laravel 12
* PHP 8.2
* Nutgram
* Filament Admin Panel
* MySQL
* Docker + Laravel Sail
* Redis (optional)

---

## Installation (Docker)

```bash
docker compose up -d
docker compose exec bot.backend composer install
docker compose exec bot.backend php artisan migrate
```

---

## Daily Usage (Sail)

```bash
./vendor/bin/sail up -d
./vendor/bin/sail down
```

---

## Environment Setup

Створи `.env`:

```
cp .env.example .env
```

Заповни:

```
APP_NAME=OLXMonitorBot
APP_ENV=local

DB_DATABASE=bot
DB_USERNAME=sail
DB_PASSWORD=password

TELEGRAM_BOT_TOKEN=your_token_here
```

---

## Running Scheduler

Для автоматичного моніторингу:

```bash
./vendor/bin/sail artisan schedule:work
```

або через cron:

```
* * * * * php artisan schedule:run
```

---

## Queue Worker (optional but recommended)

```bash
./vendor/bin/sail artisan queue:work
```

---

## Admin Panel (Filament)

Адмін-панель доступна за адресою:

```
/admin
```

Дозволяє:

* додавати рубрики для моніторингу
* керувати інтервалами перевірки
* переглядати знайдені оголошення
* керувати підписниками

---

## Example Workflow

1. додати URL рубрики OLX
2. встановити інтервал перевірки
3. бот зберігає знайдені оголошення
4. нові позиції надсилаються в Telegram

---

## Project Structure

```
app/
 ├── Bots/
 ├── Console/
 ├── Jobs/
 ├── Services/
 └── Filament/

database/
routes/
docker/
```

---

## Future Improvements

Раціональні наступні кроки розвитку:

* антидублювання через hash оголошень
* multi-region monitoring
* keyword filtering
* price change tracking
* web dashboard статистики
* rate-limit auto-tuning
* proxy support
