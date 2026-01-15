<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class TarjetaRepository
{
    /**
     * OBTENER EMPLEADOS
     * Trae la lista maestra de empleados desde BioTime.
     * Hace JOIN con departamentos y puestos para traer nombres en vez de solo IDs.
     */
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
                public.personnel_department.dept_name AS department_name, -- Nombre Depto
                public.personnel_position.position_name AS job_title      -- Nombre Puesto
            FROM public.personnel_employee
            LEFT JOIN public.personnel_position 
                ON public.personnel_position.id = public.personnel_employee.position_id
            LEFT JOIN public.personnel_department 
                ON public.personnel_employee.department_id = public.personnel_department.id
            ORDER BY public.personnel_employee.id ASC;
        ";

        // IMPORTANTE: Usa la conexión 'pgsql_biotime' definida en config/database.php
        return DB::connection('pgsql_biotime')->select($query);
    }

    /**
     * OBTENER REGISTROS DE ASISTENCIA
     * Esta es la consulta pesada.
     * - `att_payloadbase`: Contiene las checadas procesadas por BioTime.
     * - `att_timeinterval` (tt): Contiene el horario que le tocaba (hora entrada, tolerancia, etc).
     * - `att_leave` (al): Contiene si pidió vacaciones/permiso ese día.
     */
    public function getAttendanceRecords($empId, $startDate, $endDate)
    {
        $query = "
            SELECT 
                apb.*,        -- Datos de asistencia
                tt.*,         -- Datos del horario (tolerancias)
                al.*,         -- Datos de vacaciones/permisos (si existen en esa fecha)
                pe.enable_holiday -- Si el empleado tiene derecho a festivos
            FROM public.att_payloadbase apb
            LEFT JOIN public.att_timeinterval tt ON apb.timetable_id = tt.id
            -- OJO: Este JOIN de 'att_leave' es estricto por día exacto (DATE vs DATE)
            LEFT JOIN public.att_leave al ON apb.emp_id = al.employee_id AND DATE(apb.check_in) = DATE(al.start_time) 
            LEFT JOIN public.personnel_employee pe ON apb.emp_id = pe.id
            WHERE apb.att_date BETWEEN ? AND ? 
            AND apb.emp_id = ? 
            ORDER BY apb.check_in ASC;
        ";

        // Los signos de interrogación (?) se reemplazan por $startDate, $endDate y $empId de forma segura.
        return DB::connection('pgsql_biotime')->select($query, [$startDate, $endDate, $empId]);
    }

    /**
     * OBTENER DÍAS FESTIVOS
     * Simple consulta a la tabla de días feriados configurados en BioTime.
     */
    public function getHolidays($startDate, $endDate)
    {
        $query = "SELECT * FROM public.att_holiday WHERE start_date BETWEEN ? AND ?;";
        return DB::connection('pgsql_biotime')->select($query, [$startDate, $endDate]);
    }



    public function getPermissions($empId, $startDate, $endDate)
    {
        return DB::connection('pgsql_biotime')->table('personnel_employee_exception')
            ->where('employee_id', $empId)
            ->where(function($query) use ($startDate, $endDate) {
                 // Busca si hay traslape de fechas
                 $query->where('start_time', '<=', $endDate)
                       ->where('end_time', '>=', $startDate);
            })
            ->select('start_time as start_date', 'end_time as end_date', 'reason')
            ->get();
    }
}