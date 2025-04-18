<?php

namespace App\Services\Weather;

use App\Exceptions\WeatherApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WindyAdapter implements WeatherAdapterInterface
{
    /**
     * Получение прогноза погоды с Windy API
     *
     * @param  float  $lat  Широта
     * @param  float  $lon  Долгота
     * @return array Данные прогноза
     *
     * @throws WeatherApiException
     */
    public function getForecast(float $lat, float $lon): array
    {
        $config = config('weather.api.windy');

        try {
            $response = Http::retry(
                $config['retry_attempts'],
                $config['retry_delay'] * 1000
            )
                ->timeout($config['timeout'])
                ->post($config['base_url'], [
                    'lat' => $lat,
                    'lon' => $lon,
                    'model' => 'gfs', // Глобальная модель прогноза
                    'parameters' => ['wind', 'temp', 'waves'], // Запрашиваемые параметры
                    'key' => $config['key'],
                    'levels' => ['surface'],
                ]);

            if (! $response->successful()) {
                throw new WeatherApiException('Windy API вернул ошибку: '.$response->status());
            }

            return $this->normalizeData($response->json());

        } catch (\Exception $e) {
            if (! $e instanceof WeatherApiException) {
                $e = new WeatherApiException('Ошибка при запросе к Windy API: '.$e->getMessage(), 0, $e);
            }

            Log::error('Windy API error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Нормализация данных от Windy API в единый формат
     *
     * @param  array  $apiData  Исходные данные
     * @return array Унифицированные данные
     */
    public function normalizeData(array $apiData): array
    {
        $result = [];

        // Получаем временные метки
        $timestamps = $apiData['ts'] ?? [];

        // Получаем данные о температуре, ветре и волнах
        $temperatures = $apiData['temp-surface'] ?? [];
        $winds = $apiData['wind-surface'] ?? [];
        $waves = $apiData['waves'] ?? [];

        foreach ($timestamps as $index => $timestamp) {
            // Конвертируем временную метку в DateTime
            $date = new \DateTime('@'.$timestamp);
            $date->setTimezone(new \DateTimeZone('Europe/Moscow')); // UTC+3

            // Форматируем дату для группировки по дням
            $dayKey = $date->format('Y-m-d');
            $hour = (int) $date->format('H');

            // Определяем временной интервал (утро, полдень, вечер)
            $timeRange = $this->determineTimeRange($hour);
            if (! $timeRange) {
                continue; // Пропускаем, если не входит в интересующие нас интервалы
            }

            // Добавляем данные в результат
            if (! isset($result[$dayKey][$timeRange])) {
                $result[$dayKey][$timeRange] = [
                    'temps' => [],
                    'winds' => [],
                    'waves' => [],
                    'date' => $date->format('Y-m-d'),
                    'day_name' => $this->getDayName($date),
                ];
            }

            // Добавляем значения температуры, ветра и волн
            if (isset($temperatures[$index])) {
                $result[$dayKey][$timeRange]['temps'][] = $temperatures[$index];
            }

            if (isset($winds[$index])) {
                $result[$dayKey][$timeRange]['winds'][] = $winds[$index];
            }

            if (isset($waves[$index])) {
                $result[$dayKey][$timeRange]['waves'][] = $waves[$index];
            }
        }

        // Вычисляем средние значения для каждого временного интервала
        foreach ($result as $dayKey => $timeRanges) {
            foreach ($timeRanges as $timeRange => $data) {
                $result[$dayKey][$timeRange]['temp'] = ! empty($data['temps'])
                    ? round(array_sum($data['temps']) / count($data['temps']), 1)
                    : null;

                $result[$dayKey][$timeRange]['wind'] = ! empty($data['winds'])
                    ? round(array_sum($data['winds']) / count($data['winds']), 1)
                    : null;

                $result[$dayKey][$timeRange]['wave'] = ! empty($data['waves'])
                    ? round(array_sum($data['waves']) / count($data['waves']), 1)
                    : null;

                // Удаляем ненужные массивы
                unset($result[$dayKey][$timeRange]['temps'],
                    $result[$dayKey][$timeRange]['winds'],
                    $result[$dayKey][$timeRange]['waves']);
            }
        }

        return $result;
    }

    /**
     * Определяет к какому временному интервалу относится данный час
     *
     * @param  int  $hour  Час дня (0-23)
     * @return string|null Ключ временного интервала или null
     */
    private function determineTimeRange(int $hour): ?string
    {
        $timeRanges = config('weather.time_ranges');

        foreach ($timeRanges as $key => $range) {
            if ($hour >= $range['start'] && $hour < $range['end']) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Возвращает название дня недели на русском
     *
     * @param  \DateTime  $date  Дата
     * @return string Название дня недели
     */
    private function getDayName(\DateTime $date): string
    {
        $dayNames = [
            1 => 'Понедельник',
            2 => 'Вторник',
            3 => 'Среда',
            4 => 'Четверг',
            5 => 'Пятница',
            6 => 'Суббота',
            7 => 'Воскресенье',
        ];

        return $dayNames[(int) $date->format('N')];
    }
}
