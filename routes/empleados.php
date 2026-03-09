<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Foundation\Application;
use App\Http\Controllers\TarjetaController;

use Illuminate\Support\Facades\Storage;


Route::middleware(['auth', 'verified'])->group(function () {
    
    Route::get('/MiTarjeta', [TarjetaController::class, 'indexIndividual'])->name('tarjetas.mi_tarjeta');
    Route::post('/MiTarjeta/descargar', [TarjetaController::class, 'downloadPdf'])->name('tarjetas.download_pdf');
    Route::post('/api/internal/schedules', [TarjetaController::class, 'getSchedule'])->name('getSchedule');
    
});