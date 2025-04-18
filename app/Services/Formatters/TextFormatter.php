<?php

namespace App\Services\Formatters;

class TextFormatter implements WeatherFormatterInterface
{
    /**
     * Форматирование данных в текстовый формат
     *
     * @param  array  $weatherData  Данные о погоде
     * @param  string  $locationName  Название локации
     * @return string Отформатированное сообщение
     */
    public function format(array $weatherData, string $locationName): string
    {
        $message = "Прогноз погоды для {$locationName} на неделю:\n\n";

        // Сортируем дни по дате
        ksort($weatherData);

        $timeRanges = config('weather.time_ranges');

        foreach ($weatherData as $dayKey => $dayData) {
            $dayName = reset($dayData)['day_name'] ?? 'Неизвестно';
            $message .= "{$dayName}:\n";

            // Сортируем временные интервалы (утро, полдень, вечер)
            ksort($dayData);

            foreach ($dayData as $timeRangeKey => $timeData) {
                if (! isset($timeRanges[$timeRangeKey])) {
                    continue;
                }

                $timeRangeInfo = $timeRanges[$timeRangeKey];
                $emoji = $timeRangeInfo['emoji'];
                $timeName = $timeRangeInfo['name'];
                $timeRange = "({$timeRangeInfo['start']}:00–{$timeRangeInfo['end']}:00)";

                // Форматируем показатели погоды
                $temp = ($timeData['temp'] !== null) ? round($timeData['temp']).'°C' : 'Н/Д';
                $wind = ($timeData['wind'] !== null) ? round($timeData['wind']).' м/с' : 'Н/Д';
                $wave = ($timeData['wave'] !== null) ? round($timeData['wave'], 1).' м' : 'Н/Д';

                // Добавляем информацию о временном интервале
                $message .= "- {$emoji} {$timeName} {$timeRange}: Температура: {$temp}, Ветер: {$wind}, Волны: {$wave}\n";
            }

            $message .= "\n";
        }

        return $message;
    }
}
