<?php

namespace App\Services\Weather;

use App\Exceptions\WeatherApiException;
use App\Services\Weather\Concerns\NormalizesWeather;
use App\Services\Weather\WeatherServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WindyAdapter implements WeatherServiceInterface
{
    use NormalizesWeather;

    public function getForecast(float $lat, float $lon): array
    {
        $config = config('weather.api.windy');

        try {
            $response = Http::retry($config['retry_attempts'], $config['retry_delay'] * 1000)
                ->timeout($config['timeout'])
                ->post($config['base_url'], [
                    'lat'        => $lat,
                    'lon'        => $lon,
                    'model'      => 'gfs',
                    'parameters' => ['wind', 'temp', 'waves', 'ptype', 'lclouds'],
                    'key'        => $config['key'],
                    'levels'     => ['surface'],
                ]);

            if (!$response->successful()) {
                throw new WeatherApiException('Windy API вернул ошибку: ' . $response->status());
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
        $winds        = $apiData['wind-surface'] ?? [];
        $waves        = $apiData['waves_height-surface'] ?? $apiData['waves'] ?? [];
        $ptype        = $apiData['ptype-surface'] ?? [];   // тип осадков
        $lclouds      = $apiData['lclouds-surface'] ?? []; // низкая облачность, %

        foreach ($timestamps as $i => $timestamp) {
            $date = (new \DateTime('@' . intval($timestamp / 1000)))
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
                    'water_temp' => $this->estimateWaterTemp((int)$date->format('n')),
                ];
            }

            // Windy отдаёт температуру в Кельвинах — переводим в Цельсии
            if (isset($temperatures[$i])) {
                $result[$dayKey][$rangeKey]['temps'][] = $temperatures[$i] - 273.15;
            }
            if (isset($winds[$i])) {
                $result[$dayKey][$rangeKey]['winds'][] = $winds[$i];
            }
            if (isset($waves[$i])) {
                $result[$dayKey][$rangeKey]['waves'][] = $waves[$i];
            }

            $result[$dayKey][$rangeKey]['conditions'][] = $this->deriveCondition(
                $ptype[$i]   ?? null,
                $lclouds[$i] ?? null
            );
        }

        return $this->aggregate($result);
    }

    /**
     * Оценивает состояние погоды по типу осадков и облачности Windy.
     * ptype: 0 — нет осадков, 1 — дождь, 3 — фриз.дождь, 5 — снег и т.д.
     */
    private function deriveCondition(?int $ptype, ?float $lowClouds): string
    {
        if ($ptype !== null && $ptype > 0) {
            return in_array($ptype, [5, 6, 7], true) ? 'snow' : 'rain';
        }
        if ($lowClouds !== null) {
            return $lowClouds > 50 ? 'clouds' : 'clear';
        }
        return 'unknown';
    }
}