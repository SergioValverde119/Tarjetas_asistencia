<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Foundation\Application;
use App\Http\Controllers\IncidenciaController; 
use Illuminate\Support\Facades\Storage;


Route::middleware(['auth', 'verified'])->group(function () {
    
    Route::middleware(['role:admin,supervisor,capturista'])->group(function () {
        Route::get('/incidencias', [IncidenciaController::class, 'index'])->name('incidencias.index');
        Route::get('/incidencias/crear', [IncidenciaController::class, 'create'])->name('incidencias.create');
        Route::post('/incidencias', [IncidenciaController::class, 'store'])->name('incidencias.store');
        Route::post('/incidencias/categoria', [IncidenciaController::class, 'storeCategory'])->name('incidencias.category.store');
        Route::get('/incidencias/plantilla', [IncidenciaController::class, 'downloadTemplate'])->name('incidencias.template');
        Route::post('/incidencias/importar', [IncidenciaController::class, 'import'])->name('incidencias.import');
        Route::get('/incidencias/estadisticas', [IncidenciaController::class, 'statistics'])->name('incidencias.statistics');
    });

});