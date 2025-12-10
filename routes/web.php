<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\KardexController;
use App\Http\Controllers\ReglasController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\EmpleadoController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/welcome', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
    ]);
})->middleware('guest')->name('welcome');

Route::get('/', function () {
    return Inertia::render('BuscarTarjetas');
})->middleware(['auth', 'verified'])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {

    // --- MÓDULO KÁRDEX ---
    Route::prefix('kardex')->name('kardex.')->group(function () {
        Route::get('/', [KardexController::class, 'index'])->name('index');
        Route::post('/buscar', [KardexController::class, 'buscar'])->name('buscar');
        Route::get('/exportar', [KardexController::class, 'exportar'])->name('exportar');
    });

    // --- MÓDULO REGLAS Y POLÍTICAS ---
    Route::prefix('reglas')->name('reglas.')->group(function () {
        // Vista Principal y Guardado de Mapeo
        Route::get('/', [ReglasController::class, 'index'])->name('index');
        Route::post('/', [ReglasController::class, 'store'])->name('store');
        
        // Nuevas Rutas de Gestión
        Route::post('/category', [ReglasController::class, 'storeCategory'])->name('category.store');
        Route::post('/policy', [ReglasController::class, 'storePolicy'])->name('policy.store');
        Route::delete('/policy/{id}', [ReglasController::class, 'deletePolicy'])->name('policy.delete');
    });

    // --- MÓDULO NOTIFICACIONES ---
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('readAll');
    });

    Route::get('/empleado/{id}', [EmpleadoController::class, 'show'])->name('empleado.show');
});

require __DIR__.'/settings.php';