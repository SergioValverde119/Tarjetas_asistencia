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
        //  $query = "
        //       SELECT apb.*, tt.*, al.*, pe.enable_holiday
        //      FROM public.att_payloadbase apb
        //      LEFT JOIN public.att_timeinterval tt ON apb.timetable_id = tt.id
        //      LEFT JOIN public.att_leave al ON apb.emp_id = al.employee_id AND DATE(apb.check_in) = DATE(al.start_time) 
        //      LEFT JOIN public.personnel_employee pe ON apb.emp_id = pe.id
        //      WHERE apb.att_date BETWEEN ? AND ? 
        //      AND apb.emp_id = ? 
        //      ORDER BY apb.check_in ASC; 
        //  ";
        // return DB::connection('pgsql_biotime')->select($query, [$startDate, $endDate, $empId]);

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
                
                -- Procesamiento de huellas del reloj
                TO_CHAR(p.entrada, 'YYYY-MM-DD HH24:MI:SS') as clock_in,
                TO_CHAR(p.salida, 'YYYY-MM-DD HH24:MI:SS') as clock_out,

                -- Datos de horario y duración
                COALESCE(je.in_time, je.shift_in_time) as in_time,
                COALESCE(je.duration, je.shift_duration) as duration,
                je.allow_late,
                (je.fecha || ' ' || COALESCE(je.in_time, je.shift_in_time, '00:00:00'))::timestamp as check_in,
                (COALESCE(je.in_time, je.shift_in_time, '00:00:00')::time + (COALESCE(je.duration, je.shift_duration, 0) || ' minutes')::interval)::time as off_time,
                
                je.enable_holiday,
                al.apply_reason

            FROM jornada_esperada je
            LEFT JOIN LATERAL (
                SELECT 
                    MIN(punch_time) as entrada,
                    CASE WHEN MAX(punch_time) > MIN(punch_time) THEN MAX(punch_time) ELSE NULL END as salida
                FROM public.iclock_transaction 
                WHERE emp_id = je.emp_id AND punch_time::date = je.fecha
            ) p ON true
            
            -- LÓGICA DE INCIDENCIAS: Prioriza la que inicia el día actual y resta 1 segundo al final
            LEFT JOIN LATERAL (
                SELECT apply_reason 
                FROM public.att_leave 
                WHERE employee_id = je.emp_id 
                AND je.fecha BETWEEN start_time::date AND (end_time - interval '1 second')::date
                ORDER BY 
                    (start_time::date = je.fecha) DESC, -- Prioridad 1: Inicia hoy
                    (end_time - start_time) ASC,       -- Prioridad 2: La más específica/corta
                    start_time DESC                      -- Prioridad 3: La más reciente
                LIMIT 1
            ) al ON true
            
            ORDER BY je.fecha ASC;
        ";

         return DB::connection('pgsql_biotime')->select($query, [
            $startDate, // Inicio calendario
            $endDate,   // Fin calendario
            $startDate, // Inicio validación horario
            $empId      // ID Empleado
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