<?php

namespace App\Services\Telegram;

use App\Services\Formatters\WeatherFormatter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
            // пытаемся удалить предыдущее сообщение
            $this->deletePreviousMessage($chatId);

            $message = $this->formatter->format($weatherData, $locationName);

            // Отправляем и запоминаем ID нового сообщения
            $sent = $this->telegram->sendMessage([
                'chat_id'    => $chatId,
                'text'       => $message,
                'parse_mode' => 'HTML', // parse_mode HTML
            ]);

        $this->storeLastMessageId($chatId, $sent->getMessageId());

        return true;
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

    /**
     * Путь к файлу с ID последнего сообщения для конкретного чата.
     */
    private function lastMessagePath(string $chatId): string
    {
        // chatId может быть отрицательным (группы) - убираем минус для имени файла
        $safe = ltrim($chatId, '-');
        return "telegram/last_message_{$safe}.txt";
    }

    /**
     * Сохраняет ID последнего отправленного сообщения.
     */
    private function storeLastMessageId(string $chatId, int $messageId): void
    {
        Storage::put($this->lastMessagePath($chatId), (string) $messageId);
    }

    /**
     * Возвращает ID последнего сообщения или null, если его нет.
     */
    private function getLastMessageId(string $chatId): ?int
    {
        $path = $this->lastMessagePath($chatId);

        if (!Storage::exists($path)) {
            return null;
        }

        $id = trim(Storage::get($path));

        return $id !== '' ? (int) $id : null;
    }

    /**
     * Пытается удалить предыдущее сообщение бота в чате.
     * Ошибки (сообщение старше 48ч, уже удалено и т.п.) игнорируются.
     */
    private function deletePreviousMessage(string $chatId): void
    {
        $messageId = $this->getLastMessageId($chatId);

        if ($messageId === null) {
            return;
        }

        try {
            $this->telegram->deleteMessage([
                'chat_id'    => $chatId,
                'message_id' => $messageId,
            ]);
        } catch (\Exception $e) {
            // Telegram не даёт удалять сообщения старше 48 часов, а также
            // уже удалённые - это ожидаемо, просто логируем и продолжаем.
            Log::info('Не удалось удалить предыдущее сообщение (ожидаемо)', [
                'chat_id'    => $chatId,
                'message_id' => $messageId,
                'reason'     => $e->getMessage(),
            ]);
        }
    }
}
