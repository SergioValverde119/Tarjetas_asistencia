<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Foundation\Application;
use App\Http\Controllers\KardexController;
use App\Http\Controllers\ReglasController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\TarjetaController;
// use App\Http\Controllers\ReporteGlobalController; 
use App\Http\Controllers\AdminUserController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
    ]);
})->name('home');

// --- NIVEL 1: TODOS (Auth) ---
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/MiTarjeta', [TarjetaController::class, 'indexIndividual'])->name('tarjetas.mi_tarjeta');
    Route::post('/MiTarjeta/descargar', [TarjetaController::class, 'downloadPdf'])->name('tarjetas.download_pdf');
    Route::post('/api/internal/schedules', [TarjetaController::class, 'getSchedule'])->name('getSchedule');
});

// --- NIVEL 2: ADMINS (Role: admin) ---
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {

    // USUARIOS
    Route::get('/usuarios', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/usuarios/nuevo', [AdminUserController::class, 'create'])->name('users.create');
    Route::post('/usuarios', [AdminUserController::class, 'store'])->name('users.store');
    Route::get('/usuarios/{user}/editar', [AdminUserController::class, 'edit'])->name('users.edit');
    Route::put('/usuarios/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::delete('/usuarios/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    Route::post('/usuarios/check-biotime', [AdminUserController::class, 'checkBiotime'])->name('users.check_biotime');

    // TARJETAS GENERALES
    Route::get('/tarjetas-generales', function () {
        return Inertia::render('BuscarTarjetas');
    })->name('tarjetas.general');
    Route::get('/api/internal/users', [TarjetaController::class, 'getUsers']);

        Route::get('/reporte-disponibilidad', [TarjetaController::class, 'indexDisponibilidad'])->name('tarjetas.disponibilidad');
        
    // LOGS DE DESCARGAS (CORREGIDO: Descomentado y apuntando al controlador)
    Route::get('/logs-descargas', [TarjetaController::class, 'indexLogs'])->name('logs.index');

    // KÁRDEX
    Route::prefix('kardex')->name('kardex.')->group(function () {
        Route::get('/', [KardexController::class, 'index'])->name('index');
        Route::post('/buscar', [KardexController::class, 'buscar'])->name('buscar');
        Route::get('/exportar', [KardexController::class, 'exportar'])->name('exportar');
    });

    // REGLAS
    Route::prefix('reglas')->name('reglas.')->group(function () {
        Route::get('/', [ReglasController::class, 'index'])->name('index');
        Route::post('/', [ReglasController::class, 'store'])->name('store');
        Route::post('/category', [ReglasController::class, 'storeCategory'])->name('category.store');
        Route::post('/policy', [ReglasController::class, 'storePolicy'])->name('policy.store');
        Route::delete('/policy/{id}', [ReglasController::class, 'deletePolicy'])->name('policy.delete');
    });

    // NOTIFICACIONES
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('readAll');
    });

    // DETALLE EMPLEADO
    Route::get('/empleado/{id}', [EmpleadoController::class, 'show'])->name('empleado.show');
});

require __DIR__.'/settings.php';