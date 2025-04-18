<?php

namespace App\Services\Weather;

use App\Exceptions\WeatherApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenWeatherMapAdapter implements WeatherServiceInterface
{
    /**
     * Получение прогноза погоды с OpenWeatherMap API
     *
     * @param  float  $lat  Широта
     * @param  float  $lon  Долгота
     * @return array Данные прогноза
     *
     * @throws WeatherApiException
     */
    public function getForecast(float $lat, float $lon): array
    {
        $config = config('weather.api.openweathermap');

        try {
            $response = Http::retry(
                $config['retry_attempts'],
                $config['retry_delay'] * 1000
            )
                ->timeout($config['timeout'])
                ->get($config['base_url'].'/forecast', [
                    'lat' => $lat,
                    'lon' => $lon,
                    'appid' => $config['key'],
                    'units' => 'metric', // Метрическая система (градусы Цельсия)
                    'cnt' => 40, // Максимальное количество временных точек (5 дней, шаг 3 часа)
                ]);

            if (! $response->successful()) {
                throw new WeatherApiException('OpenWeatherMap API вернул ошибку: '.$response->status());
            }

            return $this->normalizeData($response->json());

        } catch (\Exception $e) {
            if (! $e instanceof WeatherApiException) {
                $e = new WeatherApiException('Ошибка при запросе к OpenWeatherMap API: '.$e->getMessage(), 0, $e);
            }

            Log::error('OpenWeatherMap API error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Нормализация данных от OpenWeatherMap API в единый формат
     *
     * @param  array  $apiData  Исходные данные
     * @return array Унифицированные данные
     */
    public function normalizeData(array $apiData): array
    {
        $result = [];

        // Получаем список прогнозов
        $forecasts = $apiData['list'] ?? [];

        foreach ($forecasts as $forecast) {
            // Конвертируем временную метку в DateTime
            $date = new \DateTime('@'.$forecast['dt']);
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

            // Добавляем значения температуры и ветра
            $result[$dayKey][$timeRange]['temps'][] = $forecast['main']['temp'];
            $result[$dayKey][$timeRange]['winds'][] = $forecast['wind']['speed'];

            // OpenWeatherMap не имеет данных о волнах в бесплатной версии
            // Можно реализовать примерный рассчет по ветру или оставить null
            $waveHeight = $this->estimateWaveHeight($forecast['wind']['speed']);
            $result[$dayKey][$timeRange]['waves'][] = $waveHeight;
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
     * Примерная оценка высоты волн на основе скорости ветра
     * Это упрощенная формула, не учитывающая множество факторов
     *
     * @param  float  $windSpeed  Скорость ветра в м/с
     * @return float Примерная высота волн в метрах
     */
    private function estimateWaveHeight(float $windSpeed): float
    {
        // Простая формула: высота волны примерно равна 0.2 * скорость ветра
        // Но не меньше 0.1 метра
        return max(0.1, $windSpeed * 0.2);
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
