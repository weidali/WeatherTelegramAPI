<?php

namespace App\Services\Weather;

use App\Exceptions\WeatherApiException;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    /**
     * @var WindyAdapter
     */
    protected $windyAdapter;

    /**
     * @var OpenWeatherMapAdapter
     */
    protected $openWeatherMapAdapter;

    /**
     * Конструктор
     */
    public function __construct(
        WindyAdapter $windyAdapter,
        OpenWeatherMapAdapter $openWeatherMapAdapter
    ) {
        $this->windyAdapter = $windyAdapter;
        $this->openWeatherMapAdapter = $openWeatherMapAdapter;
    }

    /**
     * Получение прогноза погоды с обработкой ошибок и резервными источниками
     *
     * @param  float  $lat  Широта
     * @param  float  $lon  Долгота
     * @return array Прогноз погоды
     *
     * @throws WeatherApiException Если не удалось получить данные ни от одного сервиса
     */
    public function getForecast(float $lat, float $lon): array
    {
        // Сначала пробуем Windy API
        try {
            Log::info('Запрос прогноза через Windy API', ['lat' => $lat, 'lon' => $lon]);

            return $this->windyAdapter->getForecast($lat, $lon);
        } catch (WeatherApiException $e) {
            Log::warning('Ошибка при получении данных с Windy API, пробуем OpenWeatherMap', [
                'error' => $e->getMessage(),
            ]);

            // При ошибке переключаемся на OpenWeatherMap
            try {
                Log::info('Запрос прогноза через OpenWeatherMap API', ['lat' => $lat, 'lon' => $lon]);

                return $this->openWeatherMapAdapter->getForecast($lat, $lon);
            } catch (WeatherApiException $e) {
                Log::error('Не удалось получить прогноз погоды ни от одного сервиса', [
                    'error' => $e->getMessage(),
                ]);

                // Если и здесь ошибка, выбрасываем исключение
                throw new WeatherApiException(
                    'Не удалось получить прогноз погоды. Все API недоступны.',
                    0,
                    $e
                );
            }
        }
    }

    /**
     * Проверка доступности любого API
     *
     * @return bool True если хотя бы один API доступен
     */
    public function isAnyApiAvailable(): bool
    {
        try {
            // Проверяем Windy API
            $windyConfig = config('weather.api.windy');
            if (! empty($windyConfig['key'])) {
                return true;
            }

            // Проверяем OpenWeatherMap API
            $openWeatherMapConfig = config('weather.api.openweathermap');
            if (! empty($openWeatherMapConfig['key'])) {
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Ошибка при проверке доступности API: '.$e->getMessage());

            return false;
        }
    }
}
