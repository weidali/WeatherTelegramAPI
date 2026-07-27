<?php

return [
    // Координаты (Гагрский район, N43°10'26", E40°15'46")
    'coordinates' => [
        'default' => [
            'lat' => env('WEATHER_LAT', 43.1739),
            'lon' => env('WEATHER_LON', 40.2628),
            'name' => 'Гагрского района',
            'chat_id' => env('TELEGRAM_CHAT_ID'),
            'dev_chat_id' => env('TELEGRAM__DEV_CHAT_ID'),
        ],
        // В будущем можно добавить другие локации
        // 'location2' => [
        //     'lat' => 12.3456,
        //     'lon' => 78.9012,
        //     'name' => 'Другая локация',
        //     'chat_id' => '-9876543210',
        // ],
    ],

    // Временные интервалы для прогноза
    'time_ranges' => [
        'morning' => [
            'start' => 6, // 6:00
            'end' => 11,  // 11:00
            'emoji' => '🌞',
            'name' => 'Утро',
        ],
        'noon' => [
            'start' => 11, // 11:00
            'end' => 16,   // 16:00
            'emoji' => '☀️',
            'name' => 'Полдень',
        ],
        'evening' => [
            'start' => 16, // 16:00
            'end' => 22,   // 22:00
            'emoji' => '🌙',
            'name' => 'Вечер',
        ],
    ],

    // Справочник погодных состояний: ключ => [emoji, подпись]
    // Ключи соответствуют внутренним нормализованным кодам
    'conditions' => [
        'clear'   => ['emoji' => '☀️', 'label' => 'Ясно'],
        'clouds'  => ['emoji' => '⛅', 'label' => 'Облачно'],
        'rain'    => ['emoji' => '🌧', 'label' => 'Дождь'],
        'thunder' => ['emoji' => '⛈', 'label' => 'Гроза'],
        'snow'    => ['emoji' => '❄️', 'label' => 'Снег'],
        'fog'     => ['emoji' => '🌫', 'label' => 'Туман'],
        'unknown' => ['emoji' => '🌡', 'label' => 'Н/Д'],
    ],

    // Параметры оценки температуры воды Чёрного моря по месяцам (°C, средние у берега)
    // Используется как резерв, если API не отдаёт реальную температуру воды (SST)
    'water_temp_by_month' => [
        1 => 9,  2 => 8,  3 => 9,  4 => 12, 5 => 17, 6 => 22,
        7 => 25, 8 => 26, 9 => 23, 10 => 19, 11 => 15, 12 => 11,
    ],

    // API конфигурация
    'api' => [
        'windy' => [
            'key' => env('WINDY_API_KEY'),
            'base_url' => 'https://api.windy.com/api/point-forecast/v2',
            'timeout' => 10, // секунд
            'retry_attempts' => 3,
            'retry_delay' => 5, // секунд
        ],
        'openweathermap' => [
            'key' => env('OPENWEATHERMAP_API_KEY'),
            'base_url' => 'https://api.openweathermap.org/data/2.5',
            'timeout' => 10, // секунд
            'retry_attempts' => 3,
            'retry_delay' => 5, // секунд
        ],
    ],

    // Формат сообщений (table, text, both)
    'message_format' => env('WEATHER_FORMAT', 'both'),

    // Расписание
    'schedule' => [
        // Для летнего периода (июнь-август)
        'summer' => [
            'days' => [0, 2, 4], // Воскресенье, Вторник, Четверг
            'time' => '08:00',    // UTC+3
        ],
        // Для остального года
        'default' => [
            'days' => [0, 3],    // Воскресенье, Среда
            'time' => '08:00',    // UTC+3
        ],
    ],
];
