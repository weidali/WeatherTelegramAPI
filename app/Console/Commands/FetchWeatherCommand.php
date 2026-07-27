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
            return $this->runSourceTest('windy', $location);
        }

        if ($this->option('test-openweathermap')) {
            return $this->runSourceTest('openweathermap', $location);
        }

        if ($this->option('deploy-info')) {
            $this->info('Отправка служебной информации о деплое в Telegram...');

            $now     = now()->format('Y-m-d H:i:s');
            $branch  = trim(shell_exec('git rev-parse --abbrev-ref HEAD'));
            $version = trim(shell_exec('git tag -l --sort=-v:refname | head -1'));
            $appName = config('app.name');

            // Полные данные последнего коммита
            $hash    = trim(shell_exec('git log -1 --pretty=format:"%h"'));
            $subject = trim(shell_exec('git log -1 --pretty=format:"%s"'));
            $body    = trim(shell_exec('git log -1 --pretty=format:"%b"'));
            $author  = trim(shell_exec('git log -1 --pretty=format:"%an"'));

            // Экранируем спецсимволы, чтобы не сломать Markdown-разметку
            $escape = fn (string $s): string => str_replace(['`', '_', '*', '[', ']'], ' ', $s);

            $message = "🛠 *Деплой*\n";
            $message .= "Time: `{$now}`\n";
            $message .= "App: `{$appName}`\n";
            $message .= "Version: `{$version}`\n";
            $message .= "Commit: `{$hash}` " . $escape($subject) . "\n";
            // Тело коммита добавляем только если оно непустое
            if ($body !== '') {
                $message .= "\n" . $escape($body);
            }

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

    /**
     * Тестовый прогон прогноза через конкретный источник с отправкой карточки в Telegram.
     */
    private function runSourceTest(string $source, array $location): int
    {
        $this->info("Тест источника «{$source}» для {$location['name']}...");

        try {
            $data = $this->weatherService->getForecastFrom(
                $source,
                $location['lat'],
                $location['lon']
            );

            $success = $this->telegramService->sendWeatherForecast(
                $data,
                $location['chat_id'],
                $location['name']
            );

            if ($success) {
                $this->info("Прогноз от «{$source}» отправлен в Telegram");
                return 0;
            }

            $this->error("Не удалось отправить прогноз от «{$source}»");
            return 1;
        } catch (WeatherApiException $e) {
            $msg = "Ошибка источника «{$source}»: " . $e->getMessage();
            $this->error($msg);
            $this->telegramService->sendErrorNotification($msg, $location['chat_id']);
            return 1;
        }
    }   
}
