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

// --- PÚBLICO: Página de Bienvenida ---
Route::get('/welcome', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
    ]);
})->middleware('guest')->name('welcome');

// --- TU PÁGINA PRINCIPAL (Restaurada) ---
// Al entrar a la raíz, mostramos el Buscador de Tarjetas
Route::get('/', function () {
    return Inertia::render('BuscarTarjetas');
})->middleware(['auth', 'verified'])->name('home');


// --- GRUPO DE MÓDULOS (Protegidos con Login) ---
Route::middleware(['auth', 'verified'])->group(function () {

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

    Route::get('/empleado/{id}', [EmpleadoController::class, 'show'])->name('empleado.show');


});

// --- CORRECCIÓN: Usamos settings.php en lugar de auth.php ---
require __DIR__.'/settings.php';