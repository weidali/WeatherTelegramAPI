<?php

namespace App\Formatters;

class WeatherFormatter
{
    /**
     * Форматирование данных о погоде в единое сообщение с карточками по дням.
     *
     * @param array  $weatherData  Нормализованные данные (см. адаптеры)
     * @param string $locationName Название локации
     * @return string
     */
    public function format(array $weatherData, string $locationName): string
    {
        $message = "📍 Прогноз погоды для {$locationName}:\n";

        // Сортируем дни по дате (ключ вида Y-m-d)
        ksort($weatherData);

        $timeRanges = config('weather.time_ranges');
        $conditions = config('weather.conditions');

        foreach ($weatherData as $dayData) {
            // Данные дня: имя дня и температура воды одинаковы для всех интервалов,
            // берём из первого доступного интервала
            $first = reset($dayData);
            $dayName   = $first['day_name'] ?? 'Неизвестно';
            $waterTemp = $first['water_temp'] ?? null;

            // Заголовок карточки: день + вода
            $waterStr = ($waterTemp !== null) ? "   🌊 Вода: " . round($waterTemp) . "°C" : '';
            $message .= "\n📅 {$dayName}{$waterStr}\n";

            // Проходим по интервалам в фиксированном порядке (утро, полдень, вечер)
            foreach ($timeRanges as $rangeKey => $rangeInfo) {
                if (!isset($dayData[$rangeKey])) {
                    continue; // На этот интервал нет данных (например, сегодня утро уже прошло)
                }

                $timeData = $dayData[$rangeKey];

                // Состояние погоды для интервала
                $conditionKey = $timeData['condition'] ?? 'unknown';
                $condition    = $conditions[$conditionKey] ?? $conditions['unknown'];

                // Значения показателей
                $temp = ($timeData['temp'] !== null) ? round($timeData['temp']) . '°C'   : 'Н/Д';
                $wind = ($timeData['wind'] !== null) ? round($timeData['wind']) . ' м/с' : 'Н/Д';
                $wave = ($timeData['wave'] !== null) ? round($timeData['wave'], 1) . ' м' : 'Н/Д';

                // Строка-заголовок интервала: эмодзи времени + название + состояние
                $message .= "{$rangeInfo['emoji']} {$rangeInfo['name']} · {$condition['emoji']} {$condition['label']}\n";
                // Строка данных с отступом
                $message .= "   🌡 {$temp}   💨 {$wind}   🌊 {$wave}\n";
            }
        }

        return $message;
    }
}