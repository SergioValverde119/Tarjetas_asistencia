<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Foundation\Application;
use App\Http\Controllers\KardexController;
use App\Http\Controllers\ReglasController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\TarjetaController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\IncidenciaController; 
use App\Http\Controllers\ChecadasBiometricosController;
use App\Http\Controllers\FaltaController;
use App\Http\Controllers\HorarioController;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
    ]);
})->name('home');







require __DIR__.'/empleados.php';
require __DIR__.'/monitor_disponibilidad.php';
require __DIR__.'/consulta_asistencia.php';
require __DIR__.'/capturista_incidencias.php';
require __DIR__.'/documentos.php'; 
require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
