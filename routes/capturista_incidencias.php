<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Foundation\Application;
use App\Http\Controllers\IncidenciaController; 
use Illuminate\Support\Facades\Storage;


Route::middleware(['auth', 'verified'])->group(function () {
    
    Route::middleware(['role:admin,supervisor,capturista'])->group(function () {

        Route::prefix('incidencias')->name('incidencias.')->group(function () {

            Route::get('/', [IncidenciaController::class, 'index'])->name('index');
            Route::post('/', [IncidenciaController::class, 'store'])->name('store');
            Route::post('/categoria', [IncidenciaController::class, 'storeCategory'])->name('category.store');
            Route::get('/crear', [IncidenciaController::class, 'create'])->name('create');
            Route::get('/{id}/editar', [IncidenciaController::class, 'edit'])->name('edit');
            Route::get('/estadisticas', [IncidenciaController::class, 'statistics'])->name('statistics');
            Route::get('/estadisticas/exportar', [IncidenciaController::class, 'exportStatistics'])->name('statistics.export');
            Route::get('/plantilla', [IncidenciaController::class, 'descargarPlantilla'])->name('template');
            Route::post('/importar', [IncidenciaController::class, 'importar'])->name('import');
        
        });
    });

});