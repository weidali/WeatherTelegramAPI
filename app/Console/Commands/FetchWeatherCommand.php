<?php

namespace App\Console\Commands;

use App\Exceptions\WeatherApiException;
use App\Services\Telegram\TelegramService;
use App\Services\Weather\WeatherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchWeatherCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'weather:fetch-and-send {location=default} {--test-windy : Отправить тестовое сообщение для Windy API} {--test-openweathermap : Отправить тестовое сообщение для OpenWeatherMap API} {--deploy-info}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Получение прогноза погоды и отправка его в Telegram';

    /**
     * @var WeatherService
     */
    protected $weatherService;

    /**
     * @var TelegramService
     */
    protected $telegramService;

    /**
     * Create a new command instance.
     */
    public function __construct(WeatherService $weatherService, TelegramService $telegramService)
    {
        parent::__construct();
        $this->weatherService = $weatherService;
        $this->telegramService = $telegramService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $locationKey = $this->argument('location');
        $locations = config('weather.coordinates');

        if (! isset($locations[$locationKey])) {
            $this->error("Локация '{$locationKey}' не найдена в конфигурации");

            return 1;
        }

        $location = $locations[$locationKey];
        $lat = $location['lat'];
        $lon = $location['lon'];
        $name = $location['name'];
        $chatId = $location['chat_id'];

        // Проверяем наличие тестовых флагов
        if ($this->option('test-windy')) {
            $this->info('Отправка тестового сообщения для Windy API...');
            $this->telegramService->sendTestMessage($chatId, 'Windy API');

            return 0;
        }

        if ($this->option('deploy-info')) {
            $this->info('Отправка служебной информации о деплое в Telegram...');

            $now = now()->format('Y-m-d H:i:s');
            $branch = trim(shell_exec('git rev-parse --abbrev-ref HEAD'));
            $commit = trim(shell_exec('git log -1 --pretty=format:"%h %s"'));
            $version = trim(shell_exec('/opt/php/8.2/bin/php artisan --version'));
            $appName = config('app.name');

            $message = <<<TEXT
        🛠 *Информация о деплое*
        🚀 Приложение: `$appName`
        📅 Время: `$now` Версия: `$version`
        🌿 Ветка: `$branch` (Коммит: `$commit`)
        TEXT;

            $developChatId = $location['dev_chat_id'];
            $this->telegramService->sendDeployMessage(
                $developChatId,
                $message,
                'Markdown',
            );

            return 0;
        }

        if ($this->option('test-openweathermap')) {
            $this->info('Отправка тестового сообщения для OpenWeatherMap API...');
            $this->telegramService->sendTestMessage($chatId, 'OpenWeatherMap API');

            return 0;
        }

        $this->info("Получение прогноза погоды для {$name} ({$lat}, {$lon})...");

        try {
            // Проверка доступности API
            if (! $this->weatherService->isAnyApiAvailable()) {
                $errorMessage = 'Не удалось запустить получение прогноза погоды. Отсутствуют ключи API.';
                $this->error($errorMessage);
                $this->telegramService->sendErrorNotification($errorMessage, $chatId);

                return 1;
            }

            // Получаем прогноз
            $weatherData = $this->weatherService->getForecast($lat, $lon);

            // Отправляем в Telegram
            $this->info('Отправка прогноза погоды в Telegram...');
            $success = $this->telegramService->sendWeatherForecast($weatherData, $chatId, $name);

            if ($success) {
                $this->info('Прогноз погоды успешно отправлен в Telegram');
                Log::info('Прогноз погоды успешно отправлен в Telegram', [
                    'location' => $name,
                    'chat_id' => $chatId,
                ]);

                return 0;
            } else {
                $this->error('Ошибка при отправке прогноза погоды в Telegram');

                return 1;
            }
        } catch (WeatherApiException $e) {
            $errorMessage = 'Ошибка при получении прогноза погоды: '.$e->getMessage();
            $this->error($errorMessage);
            $this->telegramService->sendErrorNotification($errorMessage, $chatId);
            Log::error($errorMessage);

            return 1;
        } catch (\Exception $e) {
            $errorMessage = 'Непредвиденная ошибка: '.$e->getMessage();
            $this->error($errorMessage);
            $this->telegramService->sendErrorNotification($errorMessage, $chatId);
            Log::error($errorMessage);

            return 1;
        }
    }
}
