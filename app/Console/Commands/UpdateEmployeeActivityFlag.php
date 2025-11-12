<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UpdateEmployeeActivityFlag extends Command
{
    // Este es el nombre que usarás en la terminal (ej: php artisan app:update-activity)
    protected $signature = 'app:update-employee-activity-flag';

    protected $description = 'Actualiza la bandera is_truly_active en la tabla personnel_employee basada en la actividad reciente.';

    // Define la conexión a tu BD copia
    protected $connection = 'pgsql_biotime';

    public function handle()
    {
        $this->info('Iniciando actualización de banderas de actividad de empleados...');

        $connection = DB::connection($this->connection);
        $fechaLimite = Carbon::now()->subMonths(3)->toDateString(); // 3 meses atrás
        $hoy = Carbon::now()->toDateString();

        // --- PASO A: Resetear todo ---
        // Primero, ponemos a TODOS como inactivos.
        $this->line('Reseteando todas las banderas a "inactivo"...');
        $connection->table('personnel_employee')->update(['is_truly_active' => false]);

        // --- PASO B: La consulta pesada ---
        // Ahora, actualizamos a 'true' SÓLO a los que cumplen la lógica.
        $this->line('Buscando empleados con actividad en los últimos 3 meses...');
        
        $query = $connection->table('personnel_employee AS e')
            ->where('e.is_active', true)      // Que BioTime piense que está activo
            ->where('e.deleted', false)     // Que no esté borrado
            ->where('e.hire_date', '<=', $hoy) // Que ya haya sido contratado
            ->whereExists(function ($subQuery) use ($fechaLimite) {
                $subQuery->select(DB::raw(1))
                         ->from('public.att_payloadbase AS p')
                         ->whereColumn('p.emp_id', 'e.id')
                         ->where('p.att_date', '>=', $fechaLimite)
                         // Buscamos "asistencias normales"
                         // (Días que no fueron falta, permiso o descanso)
                         ->where(function ($q) {
                             $q->where('p.duration', '>', 0)
                               ->orWhere('p.late', '>', 0)
                               ->orWhere('p.early_leave', '>', 0);
                         });
            });

        // Ejecutamos la actualización
        $count = $query->update(['e.is_truly_active' => true]);

        $this->info("¡Éxito! Se marcaron $count empleados como 'verdaderamente activos'.");
        return 0;
    }
}
