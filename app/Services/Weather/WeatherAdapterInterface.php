<?php

namespace App\Services\Weather;

interface WeatherAdapterInterface
{
    /**
     * Получение прогноза погоды на неделю.
     *
     * @param  float  $lat  Широта
     * @param  float  $lon  Долгота
     * @return array  Нормализованные данные прогноза
     *
     * @throws \App\Exceptions\WeatherApiException
     */
    public function getForecast(float $lat, float $lon): array;

    /**
     * Приводит сырые данные API к единому внутреннему формату.
     *
     * @param  array  $apiData  Исходный ответ API
     * @return array  Унифицированные данные
     */
    public function normalizeData(array $apiData): array;
}