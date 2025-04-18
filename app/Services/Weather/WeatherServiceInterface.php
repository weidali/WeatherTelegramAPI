<?php

namespace App\Services\Weather;

interface WeatherServiceInterface
{
    /**
     * Получение прогноза погоды на неделю
     *
     * @param  float  $lat  Широта
     * @param  float  $lon  Долгота
     * @return array Данные прогноза погоды
     *
     * @throws \App\Exceptions\WeatherApiException
     */
    public function getForecast(float $lat, float $lon): array;

    /**
     * Конвертирует данные в единый формат
     *
     * @param  array  $apiData  Исходные данные API
     * @return array Унифицированные данные
     */
    public function normalizeData(array $apiData): array;
}
