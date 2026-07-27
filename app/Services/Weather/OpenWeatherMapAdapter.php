<?php

namespace App\Services\Weather;

use App\Exceptions\WeatherApiException;
use App\Services\Weather\Concerns\NormalizesWeather;
use App\Services\Weather\WeatherServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenWeatherMapAdapter implements WeatherServiceInterface
{
    use NormalizesWeather;

    public function getForecast(float $lat, float $lon): array
    {
        $config = config('weather.api.openweathermap');

        try {
            $response = Http::retry($config['retry_attempts'], $config['retry_delay'] * 1000)
                ->timeout($config['timeout'])
                ->get($config['base_url'] . '/forecast', [
                    'lat'   => $lat,
                    'lon'   => $lon,
                    'appid' => $config['key'],
                    'units' => 'metric',
                    'cnt'   => 40,
                ]);

            if (!$response->successful()) {
                throw new WeatherApiException('OpenWeatherMap API вернул ошибку: ' . $response->status());
            }

            return $this->normalizeData($response->json());
        } catch (\Exception $e) {
            if (!$e instanceof WeatherApiException) {
                $e = new WeatherApiException('Ошибка при запросе к OpenWeatherMap API: ' . $e->getMessage(), 0, $e);
            }
            Log::error('OpenWeatherMap API error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function normalizeData(array $apiData): array
    {
        $result = [];

        foreach ($apiData['list'] ?? [] as $forecast) {
            $date = (new \DateTime('@' . $forecast['dt']))
                ->setTimezone(new \DateTimeZone('Europe/Moscow'));

            $dayKey = $date->format('Y-m-d');
            $hour   = (int)$date->format('H');

            $rangeKey = $this->determineTimeRange($hour);
            if (!$rangeKey) {
                continue;
            }

            if (!isset($result[$dayKey][$rangeKey])) {
                $result[$dayKey][$rangeKey] = [
                    'temps' => [], 'winds' => [], 'waves' => [], 'conditions' => [],
                    'date' => $dayKey,
                    'day_name' => $this->getDayName($date),
                    // Температуру воды OWM в бесплатном тарифе не даёт — оцениваем по месяцу
                    'water_temp' => $this->estimateWaterTemp((int)$date->format('n')),
                ];
            }

            $windSpeed = $forecast['wind']['speed'] ?? 0;
            $result[$dayKey][$rangeKey]['temps'][] = $forecast['main']['temp'];
            $result[$dayKey][$rangeKey]['winds'][] = $windSpeed;
            $result[$dayKey][$rangeKey]['waves'][] = $this->estimateWaveHeight($windSpeed);

            // Состояние из кода погоды OWM
            $owmId = $forecast['weather'][0]['id'] ?? 800;
            $result[$dayKey][$rangeKey]['conditions'][] = $this->mapOwmCondition($owmId);
        }

        return $this->aggregate($result);
    }

    /**
     * Преобразует числовой код погоды OpenWeatherMap во внутренний ключ состояния.
     * Группы кодов: https://openweathermap.org/weather-conditions
     */
    private function mapOwmCondition(int $id): string
    {
        return match (true) {
            $id >= 200 && $id < 300 => 'thunder', // Гроза
            $id >= 300 && $id < 600 => 'rain',    // Морось + дождь
            $id >= 600 && $id < 700 => 'snow',    // Снег
            $id >= 700 && $id < 800 => 'fog',     // Туман, дымка и пр.
            $id === 800             => 'clear',   // Ясно
            $id > 800               => 'clouds',  // Облачно
            default                 => 'unknown',
        };
    }
}