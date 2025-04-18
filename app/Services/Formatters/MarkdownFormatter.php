<?php

namespace App\Services\Formatters;

class MarkdownFormatter implements WeatherFormatterInterface
{
    /**
     * Форматирование данных в Markdown-таблицу
     *
     * @param  array  $weatherData  Данные о погоде
     * @param  string  $locationName  Название локации
     * @return string Отформатированное сообщение
     */
    public function format(array $weatherData, string $locationName): string
    {
        $message = "Прогноз погоды для {$locationName} на неделю:\n";
        $message .= "| День | Время | Температура | Ветер | Волны |\n";
        $message .= "|------|-------|-------------|-------|-------|\n";

        // Сортируем дни по дате
        ksort($weatherData);

        $timeRanges = config('weather.time_ranges');
        $previousDay = null;

        foreach ($weatherData as $dayKey => $dayData) {
            $dayName = reset($dayData)['day_name'] ?? 'Неизвестно';

            // Сортируем временные интервалы (утро, полдень, вечер)
            ksort($dayData);

            foreach ($dayData as $timeRangeKey => $timeData) {
                if (! isset($timeRanges[$timeRangeKey])) {
                    continue;
                }

                $timeRangeInfo = $timeRanges[$timeRangeKey];
                $emoji = $timeRangeInfo['emoji'];
                $timeName = $timeRangeInfo['name'];

                // Форматируем показатели погоды
                $temp = ($timeData['temp'] !== null) ? round($timeData['temp']).'°C' : 'Н/Д';
                $wind = ($timeData['wind'] !== null) ? round($timeData['wind']).' м/с' : 'Н/Д';
                $wave = ($timeData['wave'] !== null) ? round($timeData['wave'], 1).' м' : 'Н/Д';

                // Добавляем строку таблицы
                if ($previousDay !== $dayName) {
                    $message .= "| {$dayName} | {$emoji} {$timeName} | {$temp} | {$wind} | {$wave} |\n";
                } else {
                    $message .= "|  | {$emoji} {$timeName} | {$temp} | {$wind} | {$wave} |\n";
                }

                $previousDay = $dayName;
            }
        }

        return $message;
    }
}
