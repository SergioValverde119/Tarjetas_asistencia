<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Foundation\Application;
use App\Http\Controllers\TarjetaController;
use Illuminate\Support\Facades\Storage;


Route::middleware(['auth', 'verified', 'role:admin,disponibilidad'])->group(function () {
    
    // Vista del Semáforo
    Route::get('/reporte-disponibilidad', [TarjetaController::class, 'indexDisponibilidad'])->name('tarjetas.disponibilidad');
    Route::get('/api/internal/users', [TarjetaController::class, 'getUsers']);
});
