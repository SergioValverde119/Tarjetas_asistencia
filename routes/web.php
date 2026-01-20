<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Foundation\Application;
use App\Http\Controllers\KardexController;
use App\Http\Controllers\ReglasController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\TarjetaController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\IncidenciaController; 

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
    ]);
})->name('home');

// --- NIVEL 1: TODOS (Auth) ---
Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/MiTarjeta', [TarjetaController::class, 'indexIndividual'])->name('tarjetas.mi_tarjeta');
    Route::post('/MiTarjeta/descargar', [TarjetaController::class, 'downloadPdf'])->name('tarjetas.download_pdf');
    Route::post('/api/internal/schedules', [TarjetaController::class, 'getSchedule'])->name('getSchedule');
});

// --- NIVEL 2: ADMINS (Role: admin) ---
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {

    // 1. INCIDENCIAS (NUEVO MÓDULO)
    Route::get('/incidencias', [IncidenciaController::class, 'index'])->name('incidencias.index');
    Route::get('/incidencias/crear', [IncidenciaController::class, 'create'])->name('incidencias.create');
    Route::post('/incidencias', [IncidenciaController::class, 'store'])->name('incidencias.store');
    Route::post('/incidencias/categoria', [IncidenciaController::class, 'storeCategory'])->name('incidencias.category.store');

    // 2. USUARIOS
    Route::get('/usuarios', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/usuarios/nuevo', [AdminUserController::class, 'create'])->name('users.create');
    Route::post('/usuarios', [AdminUserController::class, 'store'])->name('users.store');
    Route::get('/usuarios/{user}/editar', [AdminUserController::class, 'edit'])->name('users.edit');
    Route::put('/usuarios/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::delete('/usuarios/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    Route::post('/usuarios/check-biotime', [AdminUserController::class, 'checkBiotime'])->name('users.check_biotime');

    // 3. TARJETAS GENERALES (BUSCADOR)
    // Apuntamos al controlador para aprovechar la paginación del backend
    Route::get('/tarjetas-generales', [TarjetaController::class, 'indexUsers'])->name('tarjetas.general');
    Route::get('/api/internal/users', [TarjetaController::class, 'getUsers']);

    // 4. REPORTE DE DISPONIBILIDAD (SEMÁFORO)
    Route::get('/reporte-disponibilidad', [TarjetaController::class, 'indexDisponibilidad'])->name('tarjetas.disponibilidad');
        
    // 5. LOGS
    Route::get('/logs-descargas', [TarjetaController::class, 'indexLogs'])->name('logs.index');

    // 6. KÁRDEX
    Route::prefix('kardex')->name('kardex.')->group(function () {
        Route::get('/', [KardexController::class, 'index'])->name('index');
        Route::post('/buscar', [KardexController::class, 'buscar'])->name('buscar');
        Route::get('/exportar', [KardexController::class, 'exportar'])->name('exportar');
    });

    // 7. REGLAS
    Route::prefix('reglas')->name('reglas.')->group(function () {
        Route::get('/', [ReglasController::class, 'index'])->name('index');
        Route::post('/', [ReglasController::class, 'store'])->name('store');
        Route::post('/category', [ReglasController::class, 'storeCategory'])->name('category.store');
        Route::post('/policy', [ReglasController::class, 'storePolicy'])->name('policy.store');
        Route::delete('/policy/{id}', [ReglasController::class, 'deletePolicy'])->name('policy.delete');
    });

    // 8. NOTIFICACIONES
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('readAll');
    });

    // 9. DETALLE EMPLEADO
    Route::get('/empleado/{id}', [EmpleadoController::class, 'show'])->name('empleado.show');
});


    require __DIR__.'/settings.php';
