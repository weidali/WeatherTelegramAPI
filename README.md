# WeatherTelegramAPI

```
app/
  Console/
    Commands/
      FetchWeatherCommand.php  # Команда для запуска задачи
  Services/
    Weather/
      WeatherService.php       # Основной сервис
      WindyAdapter.php         # Адаптер для Windy API
      OpenWeatherMapAdapter.php # Адаптер для OpenWeatherMap
    Telegram/
      TelegramService.php      # Сервис для работы с Telegram
  Formatters/
    WeatherFormatter.php       # Интерфейс форматирования
    MarkdownFormatter.php      # Markdown-форматирование
    TextFormatter.php          # Текстовое форматирование
  Exceptions/
    WeatherApiException.php    # Исключения для API
config/
  weather.php                  # Конфигурационный файл
  services.php                 # Настройки для сервисов
  ```