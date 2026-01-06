<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Foundation\Application; // Importante para versiones
use App\Http\Controllers\KardexController;
use App\Http\Controllers\ReglasController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\TarjetaController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// 1. PÁGINA DE BIENVENIDA (HUB PRINCIPAL)
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
    ]);
})->name('home');


// GRUPO DE RUTAS PROTEGIDAS (Solo usuarios logueados)
Route::middleware(['auth', 'verified'])->group(function () {

    // --- MI TARJETA (INDIVIDUAL) ---
    
    // 1. Vista Principal (Tabla de meses 2025)
    Route::get('/MiTarjeta', [TarjetaController::class, 'indexIndividual'])
        ->name('tarjetas.mi_tarjeta');

    // 2. Acción de Descargar PDF (Nueva ruta para el botón)
    Route::post('/MiTarjeta/descargar', [TarjetaController::class, 'downloadPdf'])
        ->name('tarjetas.download_pdf');


    // --- TARJETAS GENERALES (ADMIN/SUP) ---
    Route::get('/tarjetas-generales', function () {
        return Inertia::render('BuscarTarjetas');
    })->name('tarjetas.general');


    // --- API INTERNA PARA TARJETAS ---
    Route::get('/api/internal/users', [TarjetaController::class, 'getUsers']);
    Route::post('/api/internal/schedules', [TarjetaController::class, 'getSchedule'])
        ->name('getSchedule');


    // --- MÓDULO KÁRDEX ---
    Route::prefix('kardex')->name('kardex.')->group(function () {
        Route::get('/', [KardexController::class, 'index'])->name('index');
        Route::post('/buscar', [KardexController::class, 'buscar'])->name('buscar');
        Route::get('/exportar', [KardexController::class, 'exportar'])->name('exportar');
    });


    // --- MÓDULO REGLAS Y POLÍTICAS ---
    Route::prefix('reglas')->name('reglas.')->group(function () {
        Route::get('/', [ReglasController::class, 'index'])->name('index');
        Route::post('/', [ReglasController::class, 'store'])->name('store');
        
        Route::post('/category', [ReglasController::class, 'storeCategory'])->name('category.store');
        Route::post('/policy', [ReglasController::class, 'storePolicy'])->name('policy.store');
        Route::delete('/policy/{id}', [ReglasController::class, 'deletePolicy'])->name('policy.delete');
    });


    // --- MÓDULO NOTIFICACIONES ---
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('readAll');
    });

    // --- DETALLE DE EMPLEADO ---
    Route::get('/empleado/{id}', [EmpleadoController::class, 'show'])->name('empleado.show');
});

require __DIR__.'/settings.php';
//require __DIR__.'/auth.php'; // Descomentar si usas Breeze/Jetstream