# Weather Forecast API

API для обработки прогноза погоды с уведомлениями в Telegram.

## Описание

Приложение автоматически запрашивает прогноз погоды для морского побережья (Гагрский район) по заданным координатам, обрабатывает данные и отправляет результаты в Telegram-группу два раза в неделю, с возможностью расширения функционала.

## Требования

- PHP 8.2 или выше
- Composer
- CRON
- FTP/SFTP доступ к хостингу

## Установка

1. Клонировать репозиторий:
   ```bash
   git clone https://github.com/your-username/weather-forecast-api.git
   cd weather-forecast-api
   ```

2. Установить зависимости:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. Создать и настроить `.env` файл:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Настроить в `.env` файле:
   ```
   # Telegram Bot
   TELEGRAM_BOT_TOKEN=your_bot_token
   TELEGRAM_CHAT_ID=your_chat_id

   # Weather APIs
   WINDY_API_KEY=your_windy_api_key
   OPENWEATHERMAP_API_KEY=your_openweathermap_api_key

   # Location Coordinates (Alahadzha, Gagra District)
   WEATHER_LAT=43.1739
   WEATHER_LON=40.2628

   # Weather Message Format (table, text, both)
   WEATHER_FORMAT=both
   ```

5. Создать кэш конфигурации:
   ```bash
   php artisan config:cache
   php artisan route:cache
   ```

## Использование

### Команды Artisan

- Запуск задачи получения и отправки прогноза погоды:
  ```bash
  php artisan weather:fetch-and-send
  ```

- Отправка тестового сообщения через Windy API:
  ```bash
  php artisan weather:fetch-and-send --test-windy
  ```

- Отправка тестового сообщения через OpenWeatherMap API:
  ```bash
  php artisan weather:fetch-and-send --test-openweathermap
  ```

### Настройка CRON на shared-хостинге

Добавьте следующую строку в crontab хостинга:

```
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

Эта команда будет запускаться каждую минуту и проверять, есть ли задачи, запланированные для выполнения.

## Получение API-ключей

### Windy API

1. Перейдите на страницу [Windy API](https://api.windy.com/)
2. Зарегистрируйтесь или войдите в существующий аккаунт
3. Перейдите в раздел "API Keys" в личном кабинете
4. Создайте новый API-ключ, указав название проекта
5. Скопируйте полученный API-ключ и сохраните его в `.env` файл

### OpenWeatherMap API

1. Перейдите на страницу [OpenWeatherMap API](https://openweathermap.org/api)
2. Зарегистрируйтесь или войдите в существующий аккаунт
3. Перейдите в личный кабинет и найдите раздел "API keys"
4. Если у вас нет ключа, создайте новый
5. Скопируйте полученный API-ключ и сохраните его в `.env` файл
6. Обратите внимание, что активация ключа может занять до 2 часов

## Настройка Telegram-бота

1. Создайте бота через [@BotFather](https://t.me/BotFather) в Telegram
2. Получите токен бота и добавьте его в `.env` файл
3. Добавьте бота в нужную группу или начните с ним диалог
4. Получите ID чата/группы (можно использовать [@userinfobot](https://t.me/userinfobot))
5. Добавьте ID чата/группы в `.env` файл

## Расписание отправки

- В летний период (июнь-август): воскресенье, вторник, четверг в 8:00 утра (UTC+3)
- В остальное время: воскресенье, среда в 8:00 утра (UTC+3)

## Конфигурация

Основные настройки хранятся в файле `config/weather.php`:

- Координаты локаций
- Временные интервалы для прогноза (утро, полдень, вечер)
- Настройки API (ключи, URL, тайм-ауты)
- Формат сообщений (таблица, текст, оба)
- Расписание отправки

## Автоматический деплой

Проект настроен для автоматического деплоя через GitHub Actions. При пуше в ветку `main` код будет автоматически загружен на хостинг по FTP.
```

### Итоговая структура проекта

```
.
├── .github
│   └── workflows
│       └── deploy.yml
├── app
│   ├── Console
│   │   ├── Commands
│   │   │   └── FetchWeatherCommand.php
│   │   └── Kernel.php
│   ├── Exceptions
│   │   └── WeatherApiException.php
│   ├── Formatters
│   │   ├── WeatherFormatterInterface.php
│   │   ├── MarkdownFormatter.php
│   │   └── TextFormatter.php
│   ├── Providers
│   │   └── TelegramServiceProvider.php
│   └── Services
│       ├── Telegram
│       │   └── TelegramService.php
│       └── Weather
│           ├── WeatherService.php
│           ├── WeatherAdapterInterface.php
│           ├── WindyAdapter.php
│           └── OpenWeatherMapAdapter.php
├── config
│   ├── app.php
│   ├── services.php
│   └── weather.php
├── .env.example
└── README.md
```

### Тестирование

Для тестирования API и отправки сообщений в Telegram, можно воспользоваться следующими командами:

1. Тестирование Windy API:
```bash
php artisan weather:fetch-and-send --test-windy
```

2. Тестирование OpenWeatherMap API:
```bash
php artisan weather:fetch-and-send --test-openweathermap
```

3. Запуск полного процесса получения и отправки прогноза:
```bash
php artisan weather:fetch-and-send
```

Каждая команда будет выводить подробную информацию о ходе выполнения и возможных ошибках.
