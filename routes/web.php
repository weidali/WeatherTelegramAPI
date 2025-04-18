<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $file_composer = json_decode(file_get_contents(base_path('composer.json')), true);
    $app_name = config('app.name');
    $app_version = $file_composer['version'];

    $view = '<pre>' . $app_name . ' - '. $app_version . '</pre>';
    return $view;
});
