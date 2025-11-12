<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use App\Http\Controllers\KardexController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// --- REGLA #1: La Página de Bienvenida ---
// Esta ruta solo se mostrará si el usuario NO ha iniciado sesión.
Route::get('/welcome', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
    ]);
})->middleware('guest')->name('welcome');

Route::get('/kardex', [KardexController::class, 'index'])->middleware(['auth', 'verified'])->name('kardex.index');
Route::post('/kardex/buscar', [KardexController::class, 'buscar'])->middleware(['auth', 'verified'])->name('kardex.buscar');
 Route::get('/kardex/exportar', [KardexController::class, 'exportar'])->middleware(['auth', 'verified'])->name('kardex.exportar');
// --- REGLA #2: La Página Principal (Tu Buscador de Tarjetas) ---
// Esta es tu página principal. Está protegida. Si un usuario no ha iniciado sesión
// e intenta acceder, Laravel lo redirigirá automáticamente a '/login'.
Route::get('/', function () {
    // Asegúrate de que el nombre aquí sea el de tu componente de búsqueda.
    return Inertia::render('BuscarTarjetas');
})->middleware(['auth', 'verified'])->name('home');

require __DIR__.'/settings.php';

