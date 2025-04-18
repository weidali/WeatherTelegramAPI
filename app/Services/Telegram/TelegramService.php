<?php

namespace App\Services\Telegram;

use App\Formatters\MarkdownFormatter;
use App\Formatters\TextFormatter;
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
     * @var MarkdownFormatter
     */
    protected $markdownFormatter;

    /**
     * @var TextFormatter
     */
    protected $textFormatter;

    /**
     * Конструктор
     */
    public function __construct(
        Api $telegram,
        MarkdownFormatter $markdownFormatter,
        TextFormatter $textFormatter
    ) {
        $this->telegram = $telegram;
        $this->markdownFormatter = $markdownFormatter;
        $this->textFormatter = $textFormatter;
    }

    /**
     * Отправка прогноза погоды в Telegram
     *
     * @param  array  $weatherData  Данные о погоде
     * @param  string  $chatId  ID чата или группы
     * @param  string  $locationName  Название локации
     * @return bool Успешность отправки
     */
    public function sendWeatherForecast(array $weatherData, string $chatId, string $locationName): bool
    {
        $format = config('weather.message_format', 'both');
        $success = true;

        try {
            if ($format === 'table' || $format === 'both') {
                $markdownMessage = $this->markdownFormatter->format($weatherData, $locationName);
                $this->sendMessage($chatId, $markdownMessage, 'Markdown');
            }

            if ($format === 'text' || $format === 'both') {
                // Если отправляем оба формата, делаем паузу в 1 секунду между сообщениями
                if ($format === 'both') {
                    sleep(1);
                }

                $textMessage = $this->textFormatter->format($weatherData, $locationName);
                $this->sendMessage($chatId, $textMessage);
            }
        } catch (\Exception $e) {
            Log::error('Ошибка при отправке прогноза погоды в Telegram', [
                'error' => $e->getMessage(),
            ]);
            $success = false;
        }

        return $success;
    }

    /**
     * Отправка уведомления об ошибке в Telegram
     *
     * @param  string  $errorMessage  Сообщение об ошибке
     * @param  string  $chatId  ID чата или группы
     * @return bool Успешность отправки
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
     * @return bool Успешность отправки
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
     * Базовый метод отправки сообщения в Telegram
     *
     * @param  string  $chatId  ID чата или группы
     * @param  string  $text  Текст сообщения
     * @param  string|null  $parseMode  Режим разбора сообщения (Markdown, HTML)
     * @return bool Успешность отправки
     *
     * @throws TelegramSDKException
     */
    protected function sendMessage(string $chatId, string $text, ?string $parseMode = null): bool
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($parseMode) {
            $params['parse_mode'] = $parseMode;
        }

        $response = $this->telegram->sendMessage($params);

        return $response->isOk();
    }
}
