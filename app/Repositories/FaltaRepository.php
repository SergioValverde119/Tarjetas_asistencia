<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Repositorio especializado para el cálculo de Faltas.
 * Optimizado para cruzar calendarios masivos en PostgreSQL.
 * Primeramente Jehová Dios y Jesús Rey.
 */
class FaltaRepository
{
    protected $connection = 'pgsql_biotime';

    public function getAreas()
    {
        return DB::connection($this->connection)
            ->table('personnel_area')
            ->select('id', 'area_name', 'area_code')
            ->orderBy('area_name', 'asc')
            ->get();
    }

    public function getEmpleadosParaMonitoreo($areaId = null, $empId = null, $exclude = [])
    {
        $query = DB::connection($this->connection)
            ->table('personnel_employee as e')
            ->select('e.id', 'e.emp_code', 'e.first_name', 'e.last_name')
            ->where('e.status', 0);

        if ($areaId) $query->where('e.area_id', $areaId);

        if ($empId) {
            $query->where(function($q) use ($empId) {
                $q->where('e.id', is_numeric($empId) ? (int)$empId : 0)
                  ->orWhere('e.emp_code', (string)$empId);
            });
        } else {
            if (!empty($exclude)) {
                $query->whereNotIn('e.emp_code', (array)$exclude);
            }
        }

        return $query->orderBy('e.emp_code', 'asc')->get();
    }

    public function getAllEmployees()
    {
        return DB::connection($this->connection)
            ->table('personnel_employee as e')
            ->select('e.id', 'e.emp_code', 'e.first_name', 'e.last_name')
            ->where('e.status', 0)
            ->orderBy('e.first_name', 'asc')
            ->get();
    }

    /**
     * Consulta Maestra: Cruza el calendario con los turnos y huellas.
     */
    public function getAsistenciaConHorarioDinamico($empId, $startDate, $endDate)
    {
        try {
            $realEmployee = DB::connection($this->connection)
                ->table('personnel_employee')
                ->where('id', is_numeric($empId) ? (int)$empId : 0)
                ->orWhere('emp_code', (string)$empId)
                ->select('id', 'emp_code', 'first_name')->first();

            if (!$realEmployee) {
                return [];
            }

            $query = "
                WITH RECURSIVE calendario AS (
                    SELECT ?::date AS fecha
                    UNION ALL
                    SELECT (fecha + interval '1 day')::date FROM calendario WHERE fecha < ?::date
                ),
                info_emp AS (
                    SELECT id, department_id, enable_holiday FROM public.personnel_employee WHERE id = ?
                ),
                asignacion_diaria AS (
                    SELECT 
                        c.fecha,
                        i.id as emp_id,
                        i.enable_holiday,
                        COALESCE(
                            (SELECT sch.shift_id FROM public.att_attschedule sch 
                             WHERE sch.employee_id = i.id AND c.fecha BETWEEN sch.start_date AND sch.end_date 
                             ORDER BY sch.start_date DESC, sch.id DESC LIMIT 1),
                            (SELECT ds.shift_id FROM public.att_departmentschedule ds 
                             WHERE ds.department_id = i.department_id LIMIT 1)
                        ) as shift_id
                    FROM calendario c
                    CROSS JOIN info_emp i
                ),
                horario_final AS (
                    SELECT 
                        ad.fecha, ad.emp_id, ad.enable_holiday,
                        ti.alias as timetable_name,
                        ti.in_time,
                        ti.work_time_duration as duration
                    FROM asignacion_diaria ad
                    LEFT JOIN public.att_shiftdetail sd ON ad.shift_id = sd.shift_id 
                        AND (sd.day_index = EXTRACT(DOW FROM ad.fecha)::int OR (sd.day_index = 7 AND EXTRACT(DOW FROM ad.fecha) = 0))
                    LEFT JOIN public.att_timeinterval ti ON sd.time_interval_id = ti.id
                )
                SELECT 
                    hf.*,
                    (COALESCE(hf.in_time, '00:00:00')::time + (COALESCE(hf.duration, 0) || ' minutes')::interval)::time as off_time,
                    COALESCE((
                        SELECT STRING_AGG(TO_CHAR(punch_time, 'YYYY-MM-DD HH24:MI:SS'), ',' ORDER BY punch_time ASC)
                        FROM public.iclock_transaction 
                        WHERE emp_id = hf.emp_id AND punch_time::date = hf.fecha
                    ), '') as all_punches,
                    (SELECT cat.category_name 
                     FROM public.att_leave l 
                     JOIN public.att_leavecategory cat ON l.category_id = cat.id
                     WHERE l.employee_id = hf.emp_id 
                     AND hf.fecha BETWEEN l.start_time::date AND (l.end_time - interval '1 second')::date
                     LIMIT 1) as nombre_permiso,
                    (SELECT 1 FROM public.att_holiday h 
                     WHERE hf.fecha = h.start_date::date LIMIT 1) as es_festivo
                FROM horario_final hf
                ORDER BY hf.fecha ASC;
            ";

            return DB::connection($this->connection)->select($query, [$startDate, $endDate, $realEmployee->id]);

        } catch (\Exception $e) {
            Log::error("FaltaRepository@getAsistenciaConHorarioDinamico ERROR: " . $e->getMessage());
            return [];
        }
    }
}