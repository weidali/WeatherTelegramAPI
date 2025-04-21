<?php

namespace App\Services\Formatters;

use Illuminate\Support\Facades\Log;

class TextFormatter implements WeatherFormatterInterface
{
    /**
     * Форматирование данных в текстовый формат
     *
     * @param  array  $weatherData  Данные о погоде
     * @param  string  $locationName  Название локации
     * @return string Отформатированное сообщение
     */
    public function format_old(array $weatherData, string $locationName): string
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

    public function format(array $weatherData, string $locationName): string
    {
        $message = "📍 Прогноз погоды для {$locationName}:\n\n";

        ksort($weatherData);
        $timeRanges = config('weather.time_ranges');
        $timeOrder = array_keys($timeRanges); // ['morning', 'noon', 'evening']

        Log::debug('[format]', [$timeOrder]);

        foreach ($weatherData as $dayKey => $dayData) {
            $dayName = reset($dayData)['day_name'] ?? 'Неизвестно';
            $message .= "📅 {$dayName}\n";

            foreach ($dayData as $timeRangeKey => $timeData) {
                if (! isset($timeRanges[$timeRangeKey],$timeRanges[$timeRangeKey])) {
                    continue;
                }

                Log::debug('[format]', [$timeRangeKey => $timeData]);

                $timeRangeInfo = $timeRanges[$timeRangeKey];
                $timeData = $dayData[$timeRangeKey];

                $emoji = $timeRangeInfo['emoji'];
                $timeName = $timeRangeInfo['name'];

                $timeLabel = $emoji.' '.$timeName;

                $temp = ($timeData['temp'] !== null) ? round($timeData['temp']).'°C' : 'Н/Д';
                $wind = ($timeData['wind'] !== null) ? round($timeData['wind']).' м/с' : 'Н/Д';
                $wave = ($timeData['wave'] !== null) ? round($timeData['wave'], 1).' м' : 'Н/Д';

                $message .= "{$timeLabel}  🌡 {$temp}   💨 {$wind}   🌊 {$wave}\n";
            }

            $message .= "\n";
        }

        return trim($message);
    }

    public function format_3(array $weatherData, string $locationName): string
    {
        $message = "📍 *Прогноз погоды для {$locationName} на неделю:*\n\n";

        ksort($weatherData);
        $timeRanges = config('weather.time_ranges');

        // Заголовки таблицы
        $message .= '<code>'.str_pad('День', 12).str_pad('Температура', 12).str_pad('Ветер', 8).str_pad('Волны', 8).'</code>'.PHP_EOL;

        foreach ($weatherData as $dayKey => $dayData) {
            $dayName = reset($dayData)['day_name'] ?? 'Неизвестно';
            $message .= "<b>{$dayName}:</b>\n";

            ksort($dayData);

            foreach ($dayData as $timeRangeKey => $timeData) {
                if (! isset($timeRanges[$timeRangeKey])) {
                    continue;
                }

                $timeRangeInfo = $timeRanges[$timeRangeKey];
                $emoji = $timeRangeInfo['emoji'];
                $timeName = $timeRangeInfo['name'];

                // Температура
                $temp = ($timeData['temp'] !== null) ? round($timeData['temp']) : null;
                $tempFormatted = ($temp !== null) ? $temp.'°C' : 'Н/Д';

                // Ветер
                $wind = ($timeData['wind'] !== null) ? round($timeData['wind']).' м/с' : 'Н/Д';

                // Волны
                $wave = ($timeData['wave'] !== null) ? round($timeData['wave'], 1).' м' : 'Н/Д';

                // Формируем строку для отображения
                $row = $emoji.' '.$timeName.': ';
                $row .= str_pad($tempFormatted, 12);
                $row .= str_pad($wind, 8);
                $row .= str_pad($wave, 8);

                // Добавляем строку в таблицу
                $message .= "<code>{$row}</code>".PHP_EOL;
            }

            $message .= PHP_EOL;
        }

        return $message;
    }

    public function format_5(array $weatherData, string $locationName): string
    {
        $message = "📍 *Прогноз погоды для {$locationName} на неделю*\n\n";

        // Сортировка дней
        ksort($weatherData);
        $timeRanges = config('weather.time_ranges');

        // Заголовок таблицы
        $message .= "++==================================================++\n";
        $message .= "| День     | Время   | 🌡 Темп | 💨 Ветер | 🌊 Волны |\n";
        $message .= "++==================================================++\n";

        foreach ($weatherData as $dayKey => $dayData) {
            $dayName = mb_substr(reset($dayData)['day_name'] ?? '???', 0, 9);
            $firstRow = true;

            // Сортировка времён суток
            ksort($dayData);

            foreach ($dayData as $timeKey => $entry) {
                if (! isset($timeRanges[$timeKey])) {
                    continue;
                }

                $timeInfo = $timeRanges[$timeKey];
                $emoji = $timeInfo['emoji'];
                $timeLabel = $emoji.' '.str_pad($timeInfo['name'], 6);

                $temp = $entry['temp'] !== null ? round($entry['temp']) : 'Н/Д';
                $wind = $entry['wind'] !== null ? round($entry['wind']) : 'Н/Д';
                $wave = $entry['wave'] !== null ? round($entry['wave'], 1) : 'Н/Д';

                // Цветовые значки (упрощённые по градациям)
                $tempColor = $this->tempColor($temp);
                $windColor = $this->windColor($wind);
                $waveColor = $this->waveColor($wave);

                // Формат строки
                $message .= sprintf(
                    "| %-9s| %-8s| %s %3s°C | %s %3s м/с | %s %4s м |\n",
                    $firstRow ? $dayName : '',
                    $timeLabel,
                    $tempColor, $temp,
                    $windColor, $wind,
                    $waveColor, $wave
                );

                $firstRow = false;
            }

            $message .= "++--------------------------------------------------++\n";
        }

        return $message;
    }

    private function tempColor($temp)
    {
        if ($temp === 'Н/Д') {
            return '⚪️';
        }
        if ($temp < 10) {
            return '🔵';
        }
        if ($temp < 20) {
            return '🟢';
        }
        if ($temp < 30) {
            return '🟡';
        }

        return '🔴';
    }

    private function windColor($wind)
    {
        if ($wind === 'Н/Д') {
            return '⚪️';
        }
        if ($wind < 3) {
            return '🟦';
        }
        if ($wind < 6) {
            return '🟨';
        }

        return '🟥';
    }

    private function waveColor($wave)
    {
        if ($wave === 'Н/Д') {
            return '⚪️';
        }
        if ($wave < 0.5) {
            return '🟦';
        }
        if ($wave < 1.0) {
            return '🟩';
        }

        return '🟥';
    }
}
