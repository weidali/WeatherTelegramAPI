<?php

namespace App\Services\Telegram;

use App\Services\Formatters\WeatherFormatter;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;

class TelegramService
{
    /**
     * @var Api
     */
    protected $telegram;

    /**
     * @var WeatherFormatter
     */
    protected $formatter;

    public function __construct(Api $telegram, WeatherFormatter $formatter)
    {
        $this->telegram  = $telegram;
        $this->formatter = $formatter;
    }

    /**
     * Отправка прогноза погоды в Telegram (одно сообщение).
     *
     * @param  array   $weatherData   Данные о погоде
     * @param  string  $chatId        ID чата или группы
     * @param  string  $locationName  Название локации
     * @return bool    Успешность отправки
     */
    public function sendWeatherForecast(array $weatherData, string $chatId, string $locationName): bool
    {
        try {
            $message = $this->formatter->format($weatherData, $locationName);

            // parse_mode HTML — чтобы работал <b> в названиях дней.
            // HTML удобнее MarkdownV2: не требует экранирования . - ( ) и т.п.
            return $this->sendMessage($chatId, $message, 'HTML');
        } catch (\Exception $e) {
            Log::error('Ошибка при отправке прогноза погоды в Telegram', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Отправка уведомления об ошибке в Telegram
     *
     * @param  string  $errorMessage  Сообщение об ошибке
     * @param  string  $chatId        ID чата или группы
     * @return bool
     */
    public function sendErrorNotification(string $errorMessage, string $chatId): bool
    {
        try {
            $message = "⚠️ *Ошибка в сервисе прогноза погоды*\n\n";
            $message .= $errorMessage;

            return $this->sendMessage($chatId, $message, 'Markdown');
        } catch (\Exception $e) {
            Log::error('Ошибка при отправке уведомления об ошибке в Telegram', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Отправка тестового сообщения в Telegram
     *
     * @param  string  $chatId  ID чата или группы
     * @param  string  $source  Название источника данных
     * @return bool
     */
    public function sendTestMessage(string $chatId, string $source): bool
    {
        try {
            $message = "🔍 *Тестовое сообщение от сервиса прогноза погоды*\n\n";
            $message .= "Источник данных: $source\n";
            $message .= 'Время отправки: '.date('Y-m-d H:i:s')." (UTC+3)\n";
            $message .= 'Это тестовое сообщение для проверки работоспособности сервиса.';

            return $this->sendMessage($chatId, $message, 'Markdown');
        } catch (\Exception $e) {
            Log::error('Ошибка при отправке тестового сообщения в Telegram', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Отправка служебного сообщения о деплое в чат разработки.
     */
    public function sendDeployMessage(string $chatId, string $text, ?string $parseMode = 'Markdown'): bool
    {
        try {
            return $this->sendMessage($chatId, $text, $parseMode);
        } catch (\Exception $e) {
            Log::error('Ошибка при отправке deploy-сообщения в Telegram', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Базовый метод отправки сообщения в Telegram
     *
     * @param  string       $chatId     ID чата или группы
     * @param  string       $text       Текст сообщения
     * @param  string|null  $parseMode  Режим разбора (Markdown, HTML)
     * @return bool
     *
     * @throws TelegramSDKException
     */
    protected function sendMessage(string $chatId, string $text, ?string $parseMode = null): bool
    {
        $params = [
            'chat_id' => $chatId,
            'text'    => $text,
        ];

        if ($parseMode) {
            $params['parse_mode'] = $parseMode;
        }

        // sendMessage() возвращает объект Message и бросает исключение при ошибке.
        // Если исключения не было - сообщение доставлено успешно.
        $this->telegram->sendMessage($params);

        return true;
    }
}
