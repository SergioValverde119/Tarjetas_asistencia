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

// --- NIVEL 0: EMPLEADOS Y GENERAL (Auth) ---
// Rutas accesibles para cualquier usuario logueado
Route::middleware(['auth', 'verified'])->group(function () {
    
    // Dashboard (Página de inicio por defecto)
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // Módulo: Mi Tarjeta (Personal)
    Route::get('/MiTarjeta', [TarjetaController::class, 'indexIndividual'])->name('tarjetas.mi_tarjeta');
    Route::post('/MiTarjeta/descargar', [TarjetaController::class, 'downloadPdf'])->name('tarjetas.download_pdf');
    
    // API interna para generar el PDF (Datos del mes)
    Route::post('/api/internal/schedules', [TarjetaController::class, 'getSchedule'])->name('getSchedule');
});

// --- NIVEL 1: MONITOR DE DISPONIBILIDAD ---
// Acceso: Administradores Y Usuarios con rol 'disponibilidad'
Route::middleware(['auth', 'verified', 'role:admin,disponibilidad'])->group(function () {
    
    // Vista del Semáforo
    Route::get('/reporte-disponibilidad', [TarjetaController::class, 'indexDisponibilidad'])->name('tarjetas.disponibilidad');
    
    // API necesaria para llenar el selector de empleados en el semáforo
    Route::get('/api/internal/users', [TarjetaController::class, 'getUsers']);
});

// --- NIVEL 2: SUPERVISOR DE INCIDENCIAS ---
// Acceso: Administradores Y Usuarios con rol 'supervisor'
Route::middleware(['auth', 'verified', 'role:admin,supervisor'])->group(function () {
    
    // Gestión de Incidencias (CRUD + Importación)
    Route::get('/incidencias', [IncidenciaController::class, 'index'])->name('incidencias.index');
    Route::get('/incidencias/crear', [IncidenciaController::class, 'create'])->name('incidencias.create');
    Route::post('/incidencias', [IncidenciaController::class, 'store'])->name('incidencias.store');
    Route::post('/incidencias/categoria', [IncidenciaController::class, 'storeCategory'])->name('incidencias.category.store');
    Route::get('/incidencias/plantilla', [IncidenciaController::class, 'downloadTemplate'])->name('incidencias.template');
    Route::post('/incidencias/importar', [IncidenciaController::class, 'import'])->name('incidencias.import');
    Route::get('/incidencias/{id}/editar', [IncidenciaController::class, 'edit'])->name('incidencias.edit');
    Route::put('/incidencias/{id}', [IncidenciaController::class, 'update'])->name('incidencias.update');
});

// --- NIVEL 3: ADMINISTRADOR SUPREMO ---
// Acceso: Únicamente rol 'admin'. Control total del sistema.
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {

    // 1. GESTIÓN DE USUARIOS DEL SISTEMA
    Route::get('/usuarios', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/usuarios/nuevo', [AdminUserController::class, 'create'])->name('users.create');
    Route::post('/usuarios', [AdminUserController::class, 'store'])->name('users.store');
    Route::get('/usuarios/{user}/editar', [AdminUserController::class, 'edit'])->name('users.edit');
    Route::put('/usuarios/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::delete('/usuarios/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    Route::post('/usuarios/check-biotime', [AdminUserController::class, 'checkBiotime'])->name('users.check_biotime');

    // 2. TARJETAS GENERALES (Buscador Global)
    Route::get('/tarjetas-generales', [TarjetaController::class, 'indexUsers'])->name('tarjetas.general');

    // 3. BITÁCORA DE DESCARGAS (Logs)
    Route::get('/logs-descargas', [TarjetaController::class, 'indexLogs'])->name('logs.index');

    // 4. KÁRDEX
    Route::prefix('kardex')->name('kardex.')->group(function () {
        Route::get('/', [KardexController::class, 'index'])->name('index');
        Route::post('/buscar', [KardexController::class, 'buscar'])->name('buscar');
        Route::get('/exportar', [KardexController::class, 'exportar'])->name('exportar');
    });

    // 5. REGLAS DE NEGOCIO
    Route::prefix('reglas')->name('reglas.')->group(function () {
        Route::get('/', [ReglasController::class, 'index'])->name('index');
        Route::post('/', [ReglasController::class, 'store'])->name('store');
        Route::post('/category', [ReglasController::class, 'storeCategory'])->name('category.store');
        Route::post('/policy', [ReglasController::class, 'storePolicy'])->name('policy.store');
        Route::delete('/policy/{id}', [ReglasController::class, 'deletePolicy'])->name('policy.delete');
    });

    // 6. NOTIFICACIONES
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('readAll');
    });

    // 7. DETALLE DE EMPLEADO (Perfil BioTime)
    Route::get('/empleado/{id}', [EmpleadoController::class, 'show'])->name('empleado.show');
});


require __DIR__.'/settings.php';
