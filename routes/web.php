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

// =================================================================================================
// RUTA PÚBLICA / HOME
// =================================================================================================
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
    ]);
})->name('home');


// =================================================================================================
// NIVEL 1: EMPLEADOS Y ADMINISTRADORES (Middleware: auth, verified)
// =================================================================================================
Route::middleware(['auth', 'verified'])->group(function () {

    // --- MÓDULO INDIVIDUAL: MI TARJETA ---
    Route::get('/MiTarjeta', [TarjetaController::class, 'indexIndividual'])
        ->name('tarjetas.mi_tarjeta');

    Route::post('/MiTarjeta/descargar', [TarjetaController::class, 'downloadPdf'])
        ->name('tarjetas.download_pdf');

    // --- API INTERNA: DATOS DE ASISTENCIA INDIVIDUAL ---
    // Esta ruta es necesaria para que 'MiTarjeta' cargue los datos
    Route::post('/api/internal/schedules', [TarjetaController::class, 'getSchedule'])
        ->name('getSchedule');
});


// =================================================================================================
// NIVEL 2: SOLO ADMINISTRADORES (Middleware: auth, verified, role:admin)
// =================================================================================================
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {

    // --- LOGS DE DESCARGAS ---
    Route::get('/logs-descargas', [TarjetaController::class, 'indexLogs'])->name('logs.index');

    // --- TARJETAS GENERALES ---
    Route::get('/tarjetas-generales', function () {
        return Inertia::render('BuscarTarjetas');
    })->name('tarjetas.general');

    // --- API INTERNA: LISTADO DE USUARIOS ---
    Route::get('/api/internal/users', [TarjetaController::class, 'getUsers']);

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

    // --- VISTA DETALLE EMPLEADO ---
    Route::get('/empleado/{id}', [EmpleadoController::class, 'show'])->name('empleado.show');
});

require __DIR__.'/settings.php';