<?php

namespace App\Services\Weather\Concerns;

trait NormalizesWeather
{
    /**
     * Определяет временной интервал (morning/noon/evening) по часу.
     */
    protected function determineTimeRange(int $hour): ?string
    {
        foreach (config('weather.time_ranges') as $key => $range) {
            if ($hour >= $range['start'] && $hour < $range['end']) {
                return $key;
            }
        }
        return null;
    }

    /**
     * Русское название дня недели.
     */
    protected function getDayName(\DateTime $date): string
    {
        $names = [
            1 => 'Понедельник', 2 => 'Вторник', 3 => 'Среда', 4 => 'Четверг',
            5 => 'Пятница', 6 => 'Суббота', 7 => 'Воскресенье',
        ];
        return $names[(int)$date->format('N')];
    }

    /**
     * Резервная оценка температуры воды Чёрного моря по месяцу.
     * Используется, когда API не предоставляет реальную SST.
     */
    protected function estimateWaterTemp(int $month): float
    {
        $table = config('weather.water_temp_by_month');
        return (float)($table[$month] ?? 18);
    }

    /**
     * Резервная оценка высоты волн по скорости ветра (когда нет данных о волнах).
     */
    protected function estimateWaveHeight(float $windSpeed): float
    {
        return round(max(0.1, $windSpeed * 0.2), 1);
    }

    /**
     * Определяет доминирующее состояние по массиву кодов (берём самое частое,
     * но опасные состояния — гроза/дождь — имеют приоритет).
     */
    protected function dominantCondition(array $codes): string
    {
        if (empty($codes)) {
            return 'unknown';
        }
        // Приоритет опасных явлений
        foreach (['thunder', 'rain', 'snow', 'fog'] as $priority) {
            if (in_array($priority, $codes, true)) {
                return $priority;
            }
        }
        // Иначе — самое частое
        $counts = array_count_values($codes);
        arsort($counts);
        return array_key_first($counts);
    }

    /**
     * Вычисляет средние значения и итоговое состояние для каждого интервала дня.
     */
    protected function aggregate(array $result): array
    {
        foreach ($result as $dayKey => $timeRanges) {
            foreach ($timeRanges as $rangeKey => $data) {
                $result[$dayKey][$rangeKey]['temp'] = !empty($data['temps'])
                    ? round(array_sum($data['temps']) / count($data['temps']), 1) : null;
                $result[$dayKey][$rangeKey]['wind'] = !empty($data['winds'])
                    ? round(array_sum($data['winds']) / count($data['winds']), 1) : null;
                $result[$dayKey][$rangeKey]['wave'] = !empty($data['waves'])
                    ? round(array_sum($data['waves']) / count($data['waves']), 1) : null;
                $result[$dayKey][$rangeKey]['condition'] =
                    $this->dominantCondition($data['conditions'] ?? []);

                unset(
                    $result[$dayKey][$rangeKey]['temps'],
                    $result[$dayKey][$rangeKey]['winds'],
                    $result[$dayKey][$rangeKey]['waves'],
                    $result[$dayKey][$rangeKey]['conditions']
                );
            }
        }
        return $result;
    }
}
