<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Определяем текущий месяц для проверки летнего периода
        $isSummerPeriod = in_array(now()->month, [6, 7, 8, 9]);

        // Получаем настройки расписания из конфигурации
        $scheduleConfig = $isSummerPeriod
            ? config('weather.schedule.summer')
            : config('weather.schedule.default');

        // Формируем CRON-выражение
        $scheduleDays = implode(',', $scheduleConfig['days']);
        $scheduleTime = $scheduleConfig['time'];
        [$hour, $minute] = explode(':', $scheduleTime);

        // Применяем расписание
        $cronExpression = "{$minute} {$hour} * * {$scheduleDays}";

        $schedule->command('weather:fetch-and-send')
            ->cron($cronExpression)
            ->timezone('Europe/Moscow') // Используем часовой пояс UTC+3
            ->withoutOverlapping() // Предотвращаем перекрытие заданий
            ->runInBackground() // Запускаем в фоне для shared-хостинга
            ->appendOutputTo(storage_path('logs/scheduler.log')); // Логгируем вывод
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
