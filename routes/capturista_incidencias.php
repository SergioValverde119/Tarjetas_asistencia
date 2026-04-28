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
            Route::get('/plantilla', [IncidenciaController::class, 'downloadTemplate'])->name('template');
            Route::post('/importar', [IncidenciaController::class, 'import'])->name('import');
            Route::get('/por-horario', [IncidenciaController::class, 'createBySchedule'])->name('createBySchedule');
            Route::post('/por-horario', [IncidenciaController::class, 'storeBySchedule'])->name('storeBySchedule');
            Route::get('/por-seccion', [IncidenciaController::class, 'createBySection'])->name('createBySection');
            Route::post('/por-seccion', [IncidenciaController::class, 'storeBySection'])->name('storeBySection');
            Route::post('/por-horario/previsualizar', [IncidenciaController::class, 'previewBySchedule'])->name('previewBySchedule');
            Route::get('/por-genero', [IncidenciaController::class, 'createByGender'])->name('createByGender');
            Route::post('/por-genero', [IncidenciaController::class, 'storeByGender'])->name('storeByGender');
            Route::post('/por-genero/preview', [IncidenciaController::class, 'previewByGender'])->name('previewByGender');
        });
    });

});