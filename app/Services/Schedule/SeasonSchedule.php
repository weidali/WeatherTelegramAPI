<?php

namespace App\Services\Schedule;

use Illuminate\Support\Carbon;

class SeasonSchedule
{
    /**
     * Месяцы, считающиеся летним периodom (расширенное лето: июнь–сентябрь).
     */
    private const SUMMER_MONTHS = [6, 7, 8, 9];

    /**
     * Является ли указанная дата (или текущая) летним периодом.
     */
    public function isSummer(?Carbon $date = null): bool
    {
        $month = ($date ?? now())->month;

        return in_array($month, self::SUMMER_MONTHS, true);
    }

    /**
     * Возвращает актуальный блок настроек расписания (summer|default)
     * из config/weather.php в зависимости от сезона.
     */
    public function config(?Carbon $date = null): array
    {
        $key = $this->isSummer($date) ? 'summer' : 'default';

        return config("weather.schedule.{$key}");
    }

    /**
     * Сколько дней прогноза показывать для текущего сезона.
     */
    public function forecastDays(?Carbon $date = null): int
    {
        return (int) ($this->config($date)['forecast_days'] ?? 5);
    }

    /**
     * CRON-выражение для текущего сезона (минута час * * дни_недели).
     */
    public function cronExpression(?Carbon $date = null): string
    {
        $cfg = $this->config($date);

        $days = implode(',', $cfg['days']);
        [$hour, $minute] = explode(':', $cfg['time']);

        return "{$minute} {$hour} * * {$days}";
    }
}