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
use App\Http\Controllers\ChecadasBiometricosController;
use App\Http\Controllers\FaltaController;
use App\Http\Controllers\HorarioController;
use App\Http\Controllers\ListaAsistenciaController;
use App\Http\Controllers\ExclusionFaltaController;
use Illuminate\Support\Facades\Storage;

Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {

    Route::get('/incidencias/{id}/editar', [IncidenciaController::class, 'edit'])->name('incidencias.edit');
    Route::put('/incidencias/{id}', [IncidenciaController::class, 'update'])->name('incidencias.update');
    Route::delete('/incidencias/{id}', [IncidenciaController::class, 'destroy'])->name('incidencias.destroy');
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
    Route::get('/tarjetas/plantilla-registros', [\App\Http\Controllers\TarjetaController::class, 'descargarPlantillaRegistros'])->name('tarjetas.plantilla_registros');
    Route::post('/tarjetas/importar-registros', [\App\Http\Controllers\TarjetaController::class, 'importarRegistros'])->name('tarjetas.importar_registros');

    // 3. BITÁCORA DE DESCARGAS (Logs)
    Route::get('/logs-descargas', [TarjetaController::class, 'indexLogs'])->name('logs.index');

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

        Route::get('/horarios', [HorarioController::class, 'index'])->name('horarios.index');
        Route::post('/horarios', [HorarioController::class, 'store'])->name('horarios.store');
        Route::put('/horarios/{id}', [HorarioController::class, 'update'])->name('horarios.update');
        Route::delete('/horarios/{id}', [HorarioController::class, 'destroy'])->name('horarios.destroy');
        
        
        Route::get('/api/empleado/{nomina}/horario', [HorarioController::class, 'getHorarioEmpleado']);
        Route::get('/horarios-asignacion/{nomina}/historial', [HorarioController::class, 'historial'])->name('horarios.historial');
        Route::post('/horarios-asignacion/{nomina}/asignar', [HorarioController::class, 'asignarHorario'])->name('horarios.asignar_horario');
        Route::delete('/horarios-asignacion/borrar/{id}', [HorarioController::class, 'destroyAsignacion'])->name('horarios.asignacion.destroy');

    // 8. REPORTE DE FALTAS ACUMULADAS
    Route::get('/admin/faltas', [FaltaController::class, 'index'])->name('faltas.index');
    Route::get('/admin/faltas/exportar', [FaltaController::class, 'exportar'])->name('faltas.exportar');

    Route::prefix('asistencia')->name('asistencia.')->group(function () {
            Route::get('/', [ListaAsistenciaController::class, 'index'])->name('index');
            Route::get('/lista/{id}', [ListaAsistenciaController::class, 'show'])->name('lista');
        });

    Route::get('/faltas/exclusiones', [ExclusionFaltaController::class, 'index'])->name('exclusion.index');
    Route::post('/faltas/exclusiones', [ExclusionFaltaController::class, 'store'])->name('exclusion.store');;
    Route::delete('/faltas/exclusiones/{id}', [ExclusionFaltaController::class, 'destroy'])->name('exclusion.destroy');
});