<?php

namespace App\Services\Formatters;

interface WeatherFormatterInterface
{
    /**
     * Форматирование данных о погоде в сообщение
     *
     * @param  array  $weatherData  Данные о погоде
     * @param  string  $locationName  Название локации
     * @return string Отформатированное сообщение
     */
    public function format(array $weatherData, string $locationName): string;
}
