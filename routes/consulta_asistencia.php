<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Foundation\Application;
use App\Http\Controllers\KardexController;
use App\Http\Controllers\ChecadasBiometricosController;
use App\Http\Controllers\EmpleadoController;
use Illuminate\Support\Facades\Storage;


Route::middleware(['auth', 'verified'])->group(function () {
    
    

    Route::middleware(['role:admin,supervisor,asistencia'])->group(function () {

        Route::get('/asistencia-cruda', [ChecadasBiometricosController::class, 'index'])->name('asistencia_cruda.index');
        Route::get('/asistencia-cruda/buscar', [ChecadasBiometricosController::class, 'buscar'])->name('asistencia_cruda.buscar');
        Route::get('/asistencia-cruda/exportar', [ChecadasBiometricosController::class, 'exportar'])->name('asistencia_cruda.exportar');
        
        Route::prefix('kardex')->name('kardex.')->group(function () {
            Route::get('/', [KardexController::class, 'index'])->name('index');
            Route::post('/buscar', [KardexController::class, 'buscar'])->name('buscar');
            Route::get('/exportar', [KardexController::class, 'exportar'])->name('exportar');
        });

        Route::get('/empleado/{id}', [EmpleadoController::class, 'show'])->name('empleado.show');

    });
    

});