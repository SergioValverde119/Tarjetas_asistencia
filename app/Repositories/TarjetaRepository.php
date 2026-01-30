<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class TarjetaRepository
{
    public function getAllEmployees()
    {
        $query = "
            SELECT 
                public.personnel_employee.id AS id, 
                public.personnel_employee.first_name, 
                public.personnel_employee.last_name, 
                public.personnel_employee.emp_code, 
                public.personnel_employee.gender, 
                public.personnel_employee.birthday, 
                public.personnel_employee.hire_date, 
                public.personnel_employee.department_id, 
                public.personnel_employee.position_id,
                public.personnel_department.dept_name AS department_name,
                public.personnel_position.position_name AS job_title
            FROM public.personnel_employee
            LEFT JOIN public.personnel_position 
                ON public.personnel_position.id = public.personnel_employee.position_id
            LEFT JOIN public.personnel_department 
                ON public.personnel_employee.department_id = public.personnel_department.id
                WHERE public.personnel_employee.status = 0
            ORDER BY public.personnel_employee.id ASC;
        ";
        return DB::connection('pgsql_biotime')->select($query);
    }

 public function getAttendanceRecords($empId, $startDate, $endDate)
    {
         $query = "
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
                WHERE e.id = ?
                LIMIT 1
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
                    ti.alias as timetable_alias,
                    ti.id as timetable_id,
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
                je.fecha as att_date,
                je.timetable_alias as timetable_name,
                je.department_name,

                -- 1. TODAS LAS ASISTENCIAS EN UNA SOLA COLUMNA (Sin MIN/MAX)
                COALESCE((
                    SELECT STRING_AGG(TO_CHAR(punch_time, 'YYYY-MM-DD HH24:MI:SS'), ',' ORDER BY punch_time ASC)
                    FROM public.iclock_transaction 
                    WHERE emp_id = je.emp_id AND punch_time::date = je.fecha
                ), '') as all_punches,

                -- 2. COLUMNAS DE HORARIO (Mantenemos nombres para el Servicio)
                COALESCE(je.in_time, je.shift_in_time) as in_time,
                COALESCE(je.duration, je.shift_duration) as duration,
                je.allow_late,
                (je.fecha || ' ' || COALESCE(je.in_time, je.shift_in_time, '00:00:00'))::timestamp as check_in,
                je.enable_holiday,
                
                -- Calculamos off_time para que el Service no truene
                (COALESCE(je.in_time, je.shift_in_time, '00:00:00')::time + (COALESCE(je.duration, je.shift_duration, 0) || ' minutes')::interval)::time as off_time,

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
                    (l.end_time - l.start_time) ASC,
                    l.start_time DESC
                LIMIT 1
            ) al ON true
            
            ORDER BY je.fecha ASC;
        ";


        return DB::connection('pgsql_biotime')->select($query, [
            $startDate, $endDate, $startDate, $empId
        ]);
    }

        
        

    public function getHolidays($startDate, $endDate)
    {
        $query = "SELECT * FROM public.att_holiday WHERE start_date BETWEEN ? AND ?;";
        return DB::connection('pgsql_biotime')->select($query, [$startDate, $endDate]);
    }

    /**
     * --- OBTENER PERMISOS POR RANGO ---
     * CORRECCIÓN: Usamos la tabla 'att_leave' que es la estándar en BioTime
     */
    public function getPermissions($empId, $startDate, $endDate)
    {
        // Cambiamos 'personnel_employee_exception' por 'att_leave'
        // Cambiamos 'reason' por 'apply_reason' (así se llama usualmente la columna de descripción)
        return DB::connection('pgsql_biotime')->table('att_leave')
            ->where('employee_id', $empId)
            ->where(function($query) use ($startDate, $endDate) {
                 // Busca si hay traslape de fechas
                 $query->where('start_time', '<=', $endDate)
                       ->where('end_time', '>=', $startDate);
            })
            ->select('start_time as start_date', 'end_time as end_date', 'apply_reason as reason')
            ->get();
    }
}