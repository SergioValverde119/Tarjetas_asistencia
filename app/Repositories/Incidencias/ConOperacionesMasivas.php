<?php

namespace App\Repositories\Incidencias;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Rasgo para operaciones masivas (Horarios y Áreas).
 */
trait ConOperacionesMasivas
{
    public function getEmployeesBySchedule($fecha, $hInicio, $hFin, $tipoFiltro)
    {
        $dayOfWeek = Carbon::parse($fecha)->dayOfWeek;
        if ($dayOfWeek === 0) $dayOfWeek = 7;

        return DB::connection($this->connection)
            ->table('personnel_employee as e')
            ->join('att_shiftdetail as sd', function($join) use ($dayOfWeek, $fecha) {
                $join->on('sd.shift_id', '=', DB::raw("(
                    SELECT COALESCE(
                        (SELECT sch.shift_id FROM att_attschedule sch 
                         WHERE sch.employee_id = e.id AND '$fecha' BETWEEN sch.start_date AND sch.end_date 
                         ORDER BY sch.start_date DESC LIMIT 1),
                        (SELECT dsch.shift_id FROM att_departmentschedule dsch 
                         WHERE dsch.department_id = e.department_id LIMIT 1)
                    )
                )"))->where('sd.day_index', '=', $dayOfWeek);
            })
            ->join('att_timeinterval as ti', 'sd.time_interval_id', '=', 'ti.id')
            ->where('e.status', 0)
            ->when($tipoFiltro === 'entrada', fn($q) => $q->whereBetween('ti.in_time', [$hInicio, $hFin]))
            ->when($tipoFiltro === 'salida', fn($q) => $q->whereRaw("(ti.in_time::time + (ti.work_time_duration || ' minutes')::interval)::time BETWEEN ? AND ?", [$hInicio, $hFin]))
            ->select('e.id', 'e.emp_code', 'e.first_name', 'e.last_name', 'ti.in_time as entrada_programada',
                DB::raw("(ti.in_time::time + (ti.work_time_duration || ' minutes')::interval)::time as salida_programada"),
                'ti.alias as nombre_turno')
            ->distinct()->get();
    }

    public function getActiveEmployees($search = null)
    {
        $query = DB::connection($this->connection)->table('personnel_employee')->select('id', 'first_name', 'last_name', 'emp_code')->where('status', 0);
        if ($search) {
            $term = '%' . strtolower($search) . '%';
            $query->where(fn($q) => $q->whereRaw('LOWER(first_name) LIKE ?', [$term])->orWhereRaw('LOWER(last_name) LIKE ?', [$term])->orWhere('emp_code', 'LIKE', $term));
        } else { $query->limit(50); }
        return $query->orderBy('first_name', 'asc')->get();
    }

    public function getDepartamentos()
    {
        return DB::connection($this->connection)->table('personnel_department')->select('id', 'dept_name', 'parent_dept_id')->orderBy('dept_name', 'asc')->get();
    }

    public function getAreas()
    {
        return DB::connection($this->connection)->table('personnel_area')->select('id', 'area_name', 'area_code')->orderBy('area_name', 'asc')->get();
    }
}