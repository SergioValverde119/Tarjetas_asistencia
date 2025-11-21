<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\KardexController;
use App\Http\Controllers\ReglasController;
use App\Http\Controllers\NotificationController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// --- PÚBLICO ---
Route::get('/welcome', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
    ]);
})->middleware('guest')->name('welcome');

Route::get('/', function () {
    return redirect()->route('kardex.index');
})->middleware(['auth', 'verified'])->name('home');


// --- PRIVADO (GRUPO PRINCIPAL) ---
// CAMBIO: Usamos el middleware estándar de Laravel, más simple y seguro.
Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // --- MÓDULO KÁRDEX ---
    Route::prefix('kardex')->name('kardex.')->group(function () {
        Route::get('/', [KardexController::class, 'index'])->name('index');
        Route::post('/buscar', [KardexController::class, 'buscar'])->name('buscar');
        Route::get('/exportar', [KardexController::class, 'exportar'])->name('exportar');
    });

    // --- MÓDULO REGLAS ---
    Route::prefix('reglas')->name('reglas.')->group(function () {
        Route::get('/', [ReglasController::class, 'index'])->name('index');
        Route::post('/', [ReglasController::class, 'store'])->name('store');
    });

    // --- MÓDULO NOTIFICACIONES ---
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('readAll');
    });

});

require __DIR__.'/settings.php';