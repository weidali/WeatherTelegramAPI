<?php

namespace App\Console;

use App\Services\Schedule\SeasonSchedule;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Определяем текущий месяц для проверки летнего периода
        $season = app(SeasonSchedule::class);

        $schedule->command('weather:fetch-and-send')
            ->cron($season->cronExpression())
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
