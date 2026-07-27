<?php

namespace App\Services\Weather;

use App\Exceptions\WeatherApiException;
use App\Services\Weather\Concerns\NormalizesWeather;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WindyAdapter implements WeatherAdapterInterface
{
    use NormalizesWeather;

    public function getForecast(float $lat, float $lon): array
    {
        $config = config('weather.api.windy');

        try {
            $response = Http::retry($config['retry_attempts'], $config['retry_delay'] * 1000)
                ->timeout($config['timeout'])
                ->post($config['base_url'], [
                    'lat'   => $lat,
                    'lon'   => $lon,
                    'model' => 'gfs',
                    // Только параметры, которые принимает gfs.
                    // Волны у gfs/gfsWave для Чёрного моря недоступны — оцениваем по ветру.
                    'parameters' => ['temp', 'wind', 'ptype', 'lclouds', 'mclouds'],
                    'levels'     => ['surface'],
                    'key'        => $config['key'],
                ]);

            if (!$response->successful()) {
                throw new WeatherApiException(
                    'Windy API вернул ошибку: ' . $response->status() . ' ' . $response->body()
                );
            }

            return $this->normalizeData($response->json());
        } catch (\Exception $e) {
            if (!$e instanceof WeatherApiException) {
                $e = new WeatherApiException('Ошибка при запросе к Windy API: ' . $e->getMessage(), 0, $e);
            }
            Log::error('Windy API error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function normalizeData(array $apiData): array
    {
        $result = [];

        $timestamps   = $apiData['ts'] ?? [];
        $temperatures = $apiData['temp-surface'] ?? [];
        $windU        = $apiData['wind_u-surface'] ?? [];
        $windV        = $apiData['wind_v-surface'] ?? [];
        $ptype        = $apiData['ptype-surface'] ?? [];
        $lclouds      = $apiData['lclouds-surface'] ?? [];
        $mclouds      = $apiData['mclouds-surface'] ?? [];

        foreach ($timestamps as $i => $timestamp) {
            // Windy отдаёт метки времени в миллисекундах
            $date = (new \DateTime('@' . intval($timestamp / 1000)))
                ->setTimezone(new \DateTimeZone('Europe/Moscow'));

            $dayKey   = $date->format('Y-m-d');
            $hour     = (int) $date->format('H');
            $rangeKey = $this->determineTimeRange($hour);

            if (!$rangeKey) {
                continue;
            }

            if (!isset($result[$dayKey][$rangeKey])) {
                $result[$dayKey][$rangeKey] = [
                    'temps' => [], 'winds' => [], 'waves' => [], 'conditions' => [],
                    'date'       => $dayKey,
                    'day_name'   => $this->getDayName($date),
                    'water_temp' => $this->estimateWaterTemp((int) $date->format('n')),
                ];
            }

            // Температура: Windy отдаёт в Кельвинах
            if (isset($temperatures[$i])) {
                $result[$dayKey][$rangeKey]['temps'][] = $temperatures[$i] - 273.15;
            }

            // Скорость ветра из компонентов u/v: модуль вектора
            if (isset($windU[$i], $windV[$i])) {
                $speed = sqrt($windU[$i] ** 2 + $windV[$i] ** 2);
                $result[$dayKey][$rangeKey]['winds'][] = $speed;
                // Волны оцениваем по ветру (реальных данных для Чёрного моря нет)
                $result[$dayKey][$rangeKey]['waves'][] = $this->estimateWaveHeight($speed);
            }

            // Состояние погоды по осадкам и облачности
            $result[$dayKey][$rangeKey]['conditions'][] = $this->deriveCondition(
                $ptype[$i]   ?? null,
                $lclouds[$i] ?? null,
                $mclouds[$i] ?? null
            );
        }

        return $this->aggregate($result);
    }

    /**
     * Оценивает состояние погоды по типу осадков и облачности Windy.
     *
     * ptype: 0 — нет осадков, ненулевое — осадки (тип зависит от модели).
     * Облачность приходит долей 0..1 или процентами — нормализуем.
     */
    private function deriveCondition(?float $ptype, ?float $lowClouds, ?float $midClouds): string
    {
        // Есть осадки
        if ($ptype !== null && $ptype > 0) {
            return 'rain';
        }

        // Оцениваем облачность (берём максимум из низкой и средней)
        $clouds = max($lowClouds ?? 0, $midClouds ?? 0);

        // Windy может отдавать проценты (0..100) или долю (0..1) — приводим к процентам
        if ($clouds <= 1) {
            $clouds *= 100;
        }

        if ($clouds > 60) {
            return 'clouds';
        }
        if ($clouds >= 0) {
            return 'clear';
        }

        return 'unknown';
    }
}