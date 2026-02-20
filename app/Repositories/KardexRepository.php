<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class KardexRepository
{
    private $connection = 'pgsql_biotime';

    public function getNominas()
    {
        return DB::connection($this->connection)
            ->table('personnel_area')
            ->where('area_name', '!=', 'Default (Reservado)')
            ->orderBy('area_name')
            ->get(['id', 'area_name']);
    }

    public function getCatalogoPermisos()
    {
        return DB::connection($this->connection)
            ->table('att_leavecategory')
            ->pluck('category_name', 'report_symbol'); 
    }

    public function getDiasFestivos(Carbon $fechaInicio, Carbon $fechaFin)
    {
        return DB::connection($this->connection)
            ->table('att_holiday')
            ->whereBetween('start_date', [$fechaInicio->toDateString(), $fechaFin->toDateString()])
            ->get();
    }

    public function getHorarioActual($empleadoId)
    {
        $turnoAsignado = DB::connection($this->connection)
            ->table('att_attschedule as s')
            ->join('att_attshift as sh', 's.shift_id', '=', 'sh.id')
            ->where('s.employee_id', $empleadoId)
            ->where('s.start_date', '<=', now()->toDateString())
            ->where('s.end_date', '>=', now()->toDateString())
            ->select('sh.id', 'sh.alias as nombre_turno')
            ->first();

        if (!$turnoAsignado) return null;

        $detalles = DB::connection($this->connection)
            ->table('att_shiftdetail as sd')
            ->join('att_timeinterval as ti', 'sd.time_interval_id', '=', 'ti.id')
            ->where('sd.shift_id', $turnoAsignado->id)
            ->select(
                'sd.day_index', 
                'ti.in_time', 
                'ti.work_time_duration as duration',
                DB::raw("(ti.in_time::time + (ti.work_time_duration || ' minutes')::interval)::time as out_time")
            )
            ->orderBy('sd.day_index')
            ->get();

        return [
            'nombre' => $turnoAsignado->nombre_turno,
            'dias' => $detalles 
        ];
    }

    public function getEmpleadosPaginados(array $filtros)
    {
        return $this->getBaseEmpleadosQuery($filtros)
            ->paginate($filtros['perPage'])
            ->withQueryString();
    }

    public function getEmpleadosTodos(array $filtros)
    {
        return $this->getBaseEmpleadosQuery($filtros)->get();
    }

    private function getBaseEmpleadosQuery(array $filtros)
    {
        $fechaBase = Carbon::createFromDate((int)$filtros['ano'], (int)$filtros['mes'], 1);
        $diaInicio = ((int)$filtros['quincena'] == 2) ? 16 : 1;
        $diaFin = ((int)$filtros['quincena'] == 1) ? 15 : $fechaBase->daysInMonth;
        $inicioPeriodo = $fechaBase->copy()->day($diaInicio)->format('Y-m-d');
        $finPeriodo = $fechaBase->copy()->day($diaFin)->format('Y-m-d');
        $mostrarSinHorario = isset($filtros['sin_horario']) && filter_var($filtros['sin_horario'], FILTER_VALIDATE_BOOLEAN);

        return DB::connection($this->connection)
            ->table('personnel_employee')
            ->select(
                'personnel_employee.id', 
                'personnel_employee.emp_code', 
                'personnel_employee.first_name', 
                'personnel_employee.last_name', 
                'personnel_employee.hire_date',
                DB::raw("(
                    SELECT STRING_AGG(pa.area_name, ', ')
                    FROM public.personnel_employee_area pea
                    JOIN public.personnel_area pa ON pea.area_id = pa.id
                    WHERE pea.employee_id = personnel_employee.id
                    AND pa.area_name != 'SEDUVI' 
                ) as nomina")
            )
            ->where('personnel_employee.enable_att', true)
            ->where('personnel_employee.status', 0)
            ->where(function ($query) use ($mostrarSinHorario, $inicioPeriodo, $finPeriodo) {
                if ($mostrarSinHorario) {
                    $query->whereNotExists(function ($sub) use ($inicioPeriodo, $finPeriodo) {
                        $sub->select(DB::raw(1))->from('public.att_attschedule as s')
                            ->whereColumn('s.employee_id', 'personnel_employee.id')
                            ->where('s.end_date', '>=', $inicioPeriodo)->where('s.start_date', '<=', $finPeriodo);
                    });
                } else {
                    $query->whereExists(function ($sub) use ($inicioPeriodo, $finPeriodo) {
                        $sub->select(DB::raw(1))->from('public.att_attschedule as s')
                            ->whereColumn('s.employee_id', 'personnel_employee.id')
                            ->where('s.end_date', '>=', $inicioPeriodo)->where('s.start_date', '<=', $finPeriodo);
                    });
                }
            })
            ->when($filtros['nomina'] ?? null, function ($query, $nominaId) {
                $query->whereExists(function ($sub) use ($nominaId) {
                    $sub->select(DB::raw(1))->from('public.personnel_employee_area')
                        ->whereColumn('public.personnel_employee_area.employee_id', 'personnel_employee.id')
                        ->where('public.personnel_employee_area.area_id', $nominaId);
                });
            })
            ->when($filtros['search'], function ($query, $searchTerm) {
                $searchTerm = '%' . strtolower($searchTerm) . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where(DB::raw('LOWER(personnel_employee.first_name || \' \' || personnel_employee.last_name)'), 'LIKE', $searchTerm)
                      ->orWhere(DB::raw('LOWER(personnel_employee.last_name || \' \' || personnel_employee.first_name)'), 'LIKE', $searchTerm) 
                      ->orWhere(DB::raw('CAST(personnel_employee.emp_code AS TEXT)'), 'LIKE', $searchTerm);
                });
            })
            ->orderBy('personnel_employee.emp_code');
    }

    /**
     * ==============================================================================
     * LA CONSULTA MAESTRA (AHORA ADAPTADA PARA MÚLTIPLES EMPLEADOS EN EL KÁRDEX)
     * ==============================================================================
     * Ignoramos 'att_payloadbase' por completo y generamos la asistencia en vivo
     * usando tu estructura CTE, optimizada para un bloque de empleados.
     */
    public function getPayloadData(array $empleadoIDs, Carbon $fechaInicio, Carbon $fechaFin)
    {
        $startDate = $fechaInicio->toDateString();
        $endDate = $fechaFin->toDateString();
        
        // Creamos los signos de interrogación necesarios ( ?, ?, ? ) para la cláusula IN()
        $placeholders = implode(',', array_fill(0, count($empleadoIDs), '?'));

        $sql = "
            WITH RECURSIVE calendario_dias AS (
                SELECT ?::date AS fecha
                UNION ALL
                SELECT (fecha + interval '1 day')::date
                FROM calendario_dias
                WHERE fecha < ?::date
            ),
            asignacion_horario AS (
                SELECT 
                    e.id as emp_id,
                    e.enable_holiday,
                    pd.dept_name as department_name,
                    COALESCE(sch.shift_id, ds.shift_id) as shift_id
                FROM public.personnel_employee e
                LEFT JOIN public.personnel_department pd ON e.department_id = pd.id
                LEFT JOIN public.att_attschedule sch ON e.id = sch.employee_id 
                    AND ?::date BETWEEN sch.start_date AND sch.end_date
                LEFT JOIN public.att_departmentschedule ds ON e.department_id = ds.department_id
                WHERE e.id IN ($placeholders)
            ),
            horario_base AS (
                SELECT DISTINCT ON (sd.shift_id)
                    sd.shift_id,
                    ti.in_time as shift_in_time,
                    ti.work_time_duration as shift_duration
                FROM public.att_shiftdetail sd
                JOIN public.att_timeinterval ti ON sd.time_interval_id = ti.id
                ORDER BY sd.shift_id, sd.day_index ASC
            ),
            jornada_esperada AS (
                SELECT 
                    cd.fecha,
                    ah.emp_id,
                    ah.enable_holiday,
                    ah.department_name,
                    ti.alias as timetable_name,
                    ti.in_time,
                    ti.work_time_duration as duration, 
                    ti.allow_late,
                    hb.shift_in_time,
                    hb.shift_duration
                FROM calendario_dias cd
                CROSS JOIN asignacion_horario ah
                LEFT JOIN horario_base hb ON ah.shift_id = hb.shift_id
                LEFT JOIN public.att_shiftdetail sd ON ah.shift_id = sd.shift_id 
                    AND sd.day_index = extract(dow from cd.fecha)::int 
                LEFT JOIN public.att_timeinterval ti ON sd.time_interval_id = ti.id
            )
            SELECT 
                je.emp_id,
                je.fecha as att_date,
                je.timetable_name,
                je.department_name,

                -- Recolección de huellas crudas (Vital para procesar salidas anticipadas)
                (SELECT STRING_AGG(TO_CHAR(punch_time, 'YYYY-MM-DD HH24:MI:SS'), ',' ORDER BY punch_time ASC)
                 FROM public.iclock_transaction 
                 WHERE emp_id = je.emp_id AND punch_time::date = je.fecha) as all_punches,

                COALESCE(je.in_time, je.shift_in_time) as in_time,
                
                -- Salida teórica
                (COALESCE(je.in_time, je.shift_in_time, '00:00:00')::time + (COALESCE(je.duration, je.shift_duration, 0) || ' minutes')::interval)::time as off_time,
                
                COALESCE(je.duration, je.shift_duration) as duration,
                je.allow_late,
                je.enable_holiday,

                -- Creamos campos vacíos para que el Servicio los llene con tu lógica de Ventana de 30 min
                NULL as clock_in,
                NULL as clock_out,

                al.nombre_categoria as nombre_permiso,
                al.motivo_original as motivo_permiso

            FROM jornada_esperada je
            
            LEFT JOIN LATERAL (
                SELECT 
                    cat.category_name as nombre_categoria, 
                    l.apply_reason as motivo_original
                FROM public.att_leave l 
                JOIN public.att_leavecategory cat ON l.category_id = cat.id
                WHERE l.employee_id = je.emp_id 
                AND je.fecha BETWEEN l.start_time::date AND (l.end_time - interval '1 second')::date
                ORDER BY 
                    (l.start_time::date = je.fecha) DESC,
                    (l.end_time - l.start_time) ASC 
                LIMIT 1
            ) al ON true
            
            ORDER BY je.emp_id ASC, je.fecha ASC;
        ";

        // Parámetros: Fecha Inicio, Fecha Fin, Fecha Inicio + Arreglo de IDs
        $bindings = array_merge([$startDate, $endDate, $startDate], $empleadoIDs);

        $resultados = DB::connection($this->connection)->select($sql, $bindings);

        // Agrupamos la respuesta por empleado para que el Servicio la pueda iterar fácil
        return collect($resultados)->groupBy('emp_id');
    }

    public function getPermisos(array $empleadoIDs, Carbon $fechaInicio, Carbon $fechaFin)
    {
        return DB::connection($this->connection)
            ->table('att_leave')
            ->join('att_leavecategory', 'att_leave.category_id', '=', 'att_leavecategory.id')
            ->select('employee_id', 'start_time', 'end_time', 'report_symbol', 'att_leave.category_id') 
            ->whereIn('employee_id', $empleadoIDs)
            ->where(function ($q) use ($fechaInicio, $fechaFin) {
                $q->where('start_time', '<=', $fechaFin)->where('end_time', '>=', $fechaInicio);
            })
            ->get()
            ->groupBy('employee_id');
    }
}