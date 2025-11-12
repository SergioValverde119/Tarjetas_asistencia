<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:update-employee-activity-flag')
    ->weeklyOn(0, '3:00') // Domingos a las 3:00 AM
    ->description('Actualiza la bandera de actividad de empleados para el Kárdex.');