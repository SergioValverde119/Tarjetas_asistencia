<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Repositorio especializado para el cálculo de Faltas.
 * Optimizado para manejar empleados con múltiples áreas, departamentos y jerarquías.
 * Primeramente Jehová Dios y Jesús Rey.
 */
class FaltaRepository
{
    protected $connection = 'pgsql_biotime';

    /**
     * Obtiene los empleados para el monitoreo.
     * Modificado para incluir el nombre del departamento y soportar múltiples áreas (Nóminas).
     */
    public function getEmpleadosParaMonitoreo($areaIds = null, $empId = null, $exclude = [], $departmentId = null)
    {
        $query = DB::connection($this->connection)
            ->table('personnel_employee as e')
            // JOIN fundamental para traer el nombre del departamento requerido por la vista
            ->leftJoin('personnel_department as d', 'e.department_id', '=', 'd.id')
            ->select(
                'e.id', 
                'e.emp_code', 
                'e.first_name', 
                'e.last_name',
                'd.dept_name', // Campo vital para que no salga "N/A" en el monitor
                // LÓGICA DE PRIORIDAD: Si tiene varias áreas, ordena poniendo SEDUVI al final (1) y el resto al principio (0)
                DB::raw("(SELECT a.area_name FROM personnel_area a 
                          JOIN personnel_employee_area pea ON a.id = pea.area_id 
                          WHERE pea.employee_id = e.id 
                          ORDER BY (CASE WHEN a.area_name = 'SEDUVI' THEN 1 ELSE 0 END) ASC, a.area_name ASC 
                          LIMIT 1) as area_name")
            )
            ->where('e.status', 0); // Solo personal activo

        // 1. Filtro de Exclusión (Lista negra local)
        if (!empty($exclude) && !$empId) {
            $excludeList = array_map(fn($item) => trim((string)$item), (array)$exclude);
            $query->whereNotIn('e.emp_code', $excludeList);
        }

        // 2. Filtro por Nómina (Áreas en BioTime) - Soporta selección múltiple
        if (!empty($areaIds)) {
            $ids = is_array($areaIds) ? $areaIds : [$areaIds];
            $query->whereExists(function ($q) use ($ids) {
                $q->select(DB::raw(1))
                  ->from('personnel_employee_area')
                  ->whereColumn('employee_id', 'e.id')
                  ->whereIn('area_id', $ids);
            });
        }

        // 3. Filtro por Departamento
        if ($departmentId) {
            $query->where('e.department_id', $departmentId);
        }

        // 4. Filtro por Empleado específico
        if ($empId) {
            $query->where(function ($q) use ($empId) {
                $q->where('e.id', is_numeric($empId) ? (int)$empId : 0)
                    ->orWhere('e.emp_code', (string)$empId);
            });
        }

        return $query->orderBy('e.emp_code', 'asc')->get();
    }
    /**
     * Catálogo de departamentos para el autocompletador de la vista.
     */
    public function getDepartamentos()
    {
        return DB::connection($this->connection)
            ->table('personnel_department')
            ->select('id', 'dept_name')
            ->orderBy('dept_name', 'asc')
            ->get();
    }

    /**
     * Catálogo de empleados con departamento y área filtrada.
     */
    public function getAllEmployees($exclude = [])
    {
        $query = DB::connection($this->connection)
            ->table('personnel_employee as e')
            ->leftJoin('personnel_department as d', 'e.department_id', '=', 'd.id')
            ->select(
                'e.id', 
                'e.emp_code', 
                'e.first_name', 
                'e.last_name',
                'd.dept_name',
                DB::raw("(SELECT a.area_name FROM personnel_area a 
                          JOIN personnel_employee_area pea ON a.id = pea.area_id 
                          WHERE pea.employee_id = e.id 
                          ORDER BY (CASE WHEN a.area_name = 'SEDUVI' THEN 1 ELSE 0 END) ASC, a.area_name ASC 
                          LIMIT 1) as area_name")
            )
            ->where('e.status', 0);

        if (!empty($exclude)) {
            $excludeList = array_map(fn($item) => trim((string)$item), (array)$exclude);
            $query->whereNotIn('e.emp_code', $excludeList);
        }

        return $query->orderBy('e.first_name', 'asc')->get();
    }

    /**
     * Catálogo de Áreas (Nóminas) de BioTime.
     */
    public function getAreas()
    {
        return DB::connection($this->connection)
            ->table('personnel_area')
            ->where('area_name', '!=', 'SEDUVI') 
            ->select('id', 'area_name', 'area_code')
            ->orderBy('area_name', 'asc')
            ->get();
    }

    /**
     * Consulta Maestra de Asistencia con integración de área priorizada.
     */
    public function getAsistenciaConHorarioDinamico($empId, $startDate, $endDate)
    {
        try {
            $realEmployee = DB::connection($this->connection)
                ->table('personnel_employee')
                ->where('id', is_numeric($empId) ? (int)$empId : 0)
                ->orWhere('emp_code', (string)$empId)
                ->select('id')->first();

            if (!$realEmployee) return [];

            $query = "
                WITH RECURSIVE calendario AS (
                    SELECT ?::date AS fecha
                    UNION ALL
                    SELECT (fecha + interval '1 day')::date FROM calendario WHERE fecha < ?::date
                ),
                info_emp AS (
                    SELECT 
                        e.id, 
                        e.department_id, 
                        e.enable_holiday, 
                        (SELECT a.area_name FROM personnel_area a 
                         JOIN personnel_employee_area pea ON a.id = pea.area_id 
                         WHERE pea.employee_id = e.id 
                         ORDER BY (CASE WHEN a.area_name = 'SEDUVI' THEN 1 ELSE 0 END) ASC, a.area_name ASC 
                         LIMIT 1) as nombre_area 
                    FROM personnel_employee e
                    WHERE e.id = ?
                ),
                asignacion_diaria AS (
                    SELECT 
                        c.fecha, i.id as emp_id, i.enable_holiday, i.nombre_area,
                        COALESCE(
                            (SELECT sch.shift_id FROM att_attschedule sch 
                             WHERE sch.employee_id = i.id AND c.fecha BETWEEN sch.start_date AND sch.end_date 
                             ORDER BY sch.start_date DESC, sch.id DESC LIMIT 1),
                            (SELECT ds.shift_id FROM att_departmentschedule ds 
                             WHERE ds.department_id = i.department_id LIMIT 1)
                        ) as shift_id
                    FROM calendario c CROSS JOIN info_emp i
                ),
                horario_final AS (
                    SELECT 
                        ad.fecha, ad.emp_id, ad.enable_holiday, ad.nombre_area,
                        ti.alias as timetable_name, ti.in_time, ti.work_time_duration as duration
                    FROM asignacion_diaria ad
                    LEFT JOIN att_shiftdetail sd ON ad.shift_id = sd.shift_id 
                        AND (sd.day_index = EXTRACT(DOW FROM ad.fecha)::int OR (sd.day_index = 7 AND EXTRACT(DOW FROM ad.fecha) = 0))
                    LEFT JOIN att_timeinterval ti ON sd.time_interval_id = ti.id
                )
                SELECT 
                    hf.*,
                    (COALESCE(hf.in_time, '00:00:00')::time + (COALESCE(hf.duration, 0) || ' minutes')::interval)::time as off_time,
                    COALESCE((
                        SELECT STRING_AGG(TO_CHAR(punch_time, 'YYYY-MM-DD HH24:MI:SS'), ',' ORDER BY punch_time ASC)
                        FROM iclock_transaction 
                        WHERE emp_id = hf.emp_id AND punch_time::date = hf.fecha
                    ), '') as all_punches,
                    (SELECT cat.category_name 
                     FROM att_leave l 
                     JOIN att_leavecategory cat ON l.category_id = cat.id
                     WHERE l.employee_id = hf.emp_id 
                     AND hf.fecha BETWEEN l.start_time::date AND (l.end_time - interval '1 second')::date
                     LIMIT 1) as nombre_permiso,
                    (SELECT 1 FROM att_holiday h 
                     WHERE hf.fecha = h.start_date::date LIMIT 1) as es_festivo
                FROM horario_final hf
                ORDER BY hf.fecha ASC;
            ";

            return DB::connection($this->connection)->select($query, [$startDate, $endDate, $realEmployee->id]);

        } catch (\Exception $e) {
            Log::error("FaltaRepository ERROR: " . $e->getMessage());
            return [];
        }
    }
}