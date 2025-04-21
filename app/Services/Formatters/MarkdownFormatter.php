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
    public function format_init(array $weatherData, string $locationName): string
    {
        $message = "Прогноз погоды для {$locationName}:\n";
        $message .= "| День | Время | Температура | Ветер | Волны |\n";
        $message .= "|------|-------|-------------|-------|-------|\n";

        // Сортируем дни по дате
        ksort($weatherData);

        $timeRanges = config('weather.time_ranges');
        $previousDay = null;

        foreach ($weatherData as $dayKey => $dayData) {
            $dayName = reset($dayData)['day_name'] ?? 'Неизвестно';
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

    public function format_2(array $weatherData, string $locationName): string
    {
        $message = "Прогноз погоды для *{$locationName}* на неделю:\n\n";
        $message .= "| **День** | **Время** | **Температура** | **Ветер** | **Волны** |\n";
        $message .= "|----------|-----------|-----------------|-----------|-----------|\n";

        // Сортируем дни по дате
        ksort($weatherData);

        $timeRanges = config('weather.time_ranges');
        $previousDay = null;

        foreach ($weatherData as $dayKey => $dayData) {
            $dayName = reset($dayData)['day_name'] ?? 'Неизвестно';

            // Добавляем день как заголовок, если он не был добавлен
            if ($previousDay !== $dayName) {
                $message .= "\n| *{$dayName}* | | | | |\n"; // Заголовок дня
            }

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

                // Добавляем строку таблицы с эмодзи
                $message .= "|  | {$emoji} *{$timeName}* | **{$temp}** | **{$wind}** | **{$wave}** |\n";
            }

            $previousDay = $dayName;
        }

        return $message;
    }

    public function format(array $weatherData, string $locationName): string
    {
        $message = "📍 *Прогноз погоды для {$locationName} на неделю:*\n\n";

        ksort($weatherData);
        $timeRanges = config('weather.time_ranges');

        // Выравнивание для столбцов
        $columnWidth = 12; // Ширина каждого столбца (для выравнивания)

        foreach ($weatherData as $dayKey => $dayData) {
            $dayName = reset($dayData)['day_name'] ?? 'Неизвестно';
            $message .= "📅 *{$dayName}*\n";

            ksort($dayData);

            foreach ($dayData as $timeRangeKey => $timeData) {
                if (! isset($timeRanges[$timeRangeKey])) {
                    continue;
                }

                $timeRangeInfo = $timeRanges[$timeRangeKey];
                $emoji = $timeRangeInfo['emoji'];
                $timeName = $timeRangeInfo['name'];

                // Определяем цвет для температуры
                $temp = ($timeData['temp'] !== null) ? round($timeData['temp']) : null;
                if ($temp !== null) {
                    // Цвет для температуры (по аналогии с Windy)
                    if ($temp > 25) {
                        $tempFormatted = "🟧 *{$temp}°C*";
                    } elseif ($temp < 10) {
                        $tempFormatted = "🟨 _{$temp}°C_";
                    } else {
                        $tempFormatted = "🟦 {$temp}°C";
                    }
                } else {
                    $tempFormatted = 'Н/Д';
                }

                // Ветер
                $wind = ($timeData['wind'] !== null) ? round($timeData['wind']).' м/с' : 'Н/Д';
                $windColor = $this->getWindColor($timeData['wind']);
                $windFormatted = "{$windColor} {$wind}";

                // Волны
                $wave = ($timeData['wave'] !== null) ? round($timeData['wave'], 1).' м' : 'Н/Д';
                $waveColor = $this->getWaveColor($timeData['wave']);
                $waveFormatted = "{$waveColor} {$wave}";

                // Формируем сообщение с выравниванием
                $message .= "{$emoji} *{$timeName}:* "
                          .str_pad($tempFormatted, $columnWidth)
                          .str_pad($windFormatted, $columnWidth)
                          .str_pad($waveFormatted, $columnWidth)."\n";
            }

            $message .= "\n";
        }

        return trim($message);
    }

    // Функция для определения цвета ветра по шкале Windy
    private function getWindColor($windSpeed): string
    {
        if ($windSpeed <= 3) {
            return '🟦'; // Легкий ветер
        } elseif ($windSpeed <= 8) {
            return '🟧'; // Средний ветер
        } else {
            return '🟥'; // Сильный ветер
        }
    }

    // Функция для определения цвета волн по шкале Windy
    private function getWaveColor($waveHeight): string
    {
        if ($waveHeight <= 0.5) {
            return '🟩'; // Маленькие волны
        } elseif ($waveHeight <= 1.5) {
            return '🟧'; // Средние волны
        } else {
            return '🟥'; // Большие волны
        }
    }
}
