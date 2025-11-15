<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use App\Http\Controllers\KardexController;
use App\Http\Controllers\ReglasController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// --- REGLA #1: La Página de Bienvenida ---
// Esta ruta solo se mostrará si el usuario NO ha iniciado sesión.
Route::get('/welcome', function () {
    return Inertia::render('Welcome', ['canLogin' => Route::has('login'),]);
})->middleware('guest')->name('welcome');

Route::get('/kardex', [KardexController::class, 'index'])->middleware(['auth', 'verified'])->name('kardex.index');
Route::post('/kardex/buscar', [KardexController::class, 'buscar'])->middleware(['auth', 'verified'])->name('kardex.buscar');
Route::get('/kardex/exportar', [KardexController::class, 'exportar'])->middleware(['auth', 'verified'])->name('kardex.exportar');
Route::get('/', function () {return Inertia::render('BuscarTarjetas');})->middleware(['auth', 'verified'])->name('home');

Route::prefix('reglas')->name('reglas.')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [ReglasController::class, 'index'])->name('index');   
    Route::post('/', [ReglasController::class, 'store'])->name('store');
});

require __DIR__.'/settings.php';

