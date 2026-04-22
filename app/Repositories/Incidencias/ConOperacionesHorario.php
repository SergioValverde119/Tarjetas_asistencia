<?php

namespace App\Repositories\Incidencias;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Rasgo (Trait) para la nueva funcionalidad de Inyección por Horario.
 * Mantiene la lógica aislada para no interferir con el código original.
 * Primeramente Jehová Dios y Jesús Rey.
 */
trait ConOperacionesHorario
{
    /**
     * Extrae empleados que tengan una jornada programada específica hoy en BioTime.
     */
    public function getEmployeesBySchedule($fecha, $hInicio, $hFin, $tipoFiltro)
    {
        // BioTime Index: 1=Lun, 7=Dom. Carbon: 1=Lun, 0=Dom.
        $diaSemana = Carbon::parse($fecha)->dayOfWeek;
        if ($diaSemana === 0) $diaSemana = 7; 

        return DB::connection($this->connection)
            ->table('personnel_employee as e')
            ->join('att_shiftdetail as sd', function($join) use ($diaSemana, $fecha) {
                // Resolución jerárquica: Prioridad Individual -> Fallback Departamento
                $join->on('sd.shift_id', '=', DB::raw("(
                    SELECT COALESCE(
                        (SELECT sch.shift_id FROM att_attschedule sch 
                         WHERE sch.employee_id = e.id AND '$fecha' BETWEEN sch.start_date AND sch.end_date 
                         ORDER BY sch.start_date DESC LIMIT 1),
                        (SELECT dsch.shift_id FROM att_departmentschedule dsch 
                         WHERE dsch.department_id = e.department_id LIMIT 1)
                    )
                )"))->where('sd.day_index', '=', $diaSemana);
            })
            ->join('att_timeinterval as ti', 'sd.time_interval_id', '=', 'ti.id')
            ->where('e.status', 0) // Empleados activos
            ->when($tipoFiltro === 'entrada', fn($q) => $q->whereBetween('ti.in_time', [$hInicio, $hFin]))
            ->when($tipoFiltro === 'salida', fn($q) => $q->whereRaw("(ti.in_time::time + (ti.work_time_duration || ' minutes')::interval)::time BETWEEN ? AND ?", [$hInicio, $hFin]))
            ->select(
                'e.id', 
                'e.emp_code', 
                'e.first_name', 
                'e.last_name',
                'ti.in_time as entrada_programada',
                DB::raw("(ti.in_time::time + (ti.work_time_duration || ' minutes')::interval)::time as salida_programada"),
                'ti.alias as nombre_turno'
            )
            ->distinct()->get();
    }
}