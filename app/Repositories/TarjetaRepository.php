<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class TarjetaRepository
{
    /**
     * Obtiene la lista de empleados usando la conexión 'pgsql_biotime'.
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
                public.personnel_department.dept_name AS department_name,
                public.personnel_position.position_name AS job_title
            FROM public.personnel_employee
            LEFT JOIN public.personnel_position 
                ON public.personnel_position.id = public.personnel_employee.position_id
            LEFT JOIN public.personnel_department 
                ON public.personnel_employee.department_id = public.personnel_department.id
            ORDER BY public.personnel_employee.id ASC;
        ";

        // Usamos explícitamente la conexión definida en config/database.php
        return DB::connection('pgsql_biotime')->select($query);
    }

    /**
     * Obtiene los registros de asistencia crudos usando la conexión 'pgsql_biotime'.
     */
    public function getAttendanceRecords($empId, $startDate, $endDate)
    {
        $query = "
            SELECT apb.*, tt.*, al.*, pe.enable_holiday
            FROM public.att_payloadbase apb
            LEFT JOIN public.att_timeinterval tt ON apb.timetable_id = tt.id
            LEFT JOIN public.att_leave al ON apb.emp_id = al.employee_id AND DATE(apb.check_in) = DATE(al.start_time) 
            LEFT JOIN public.personnel_employee pe ON apb.emp_id = pe.id
            WHERE apb.att_date BETWEEN ? AND ? 
            AND apb.emp_id = ? 
            ORDER BY apb.check_in ASC;
        ";

        return DB::connection('pgsql_biotime')->select($query, [$startDate, $endDate, $empId]);
    }

    /**
     * Obtiene los días festivos usando la conexión 'pgsql_biotime'.
     */
    public function getHolidays($startDate, $endDate)
    {
        $query = "SELECT * FROM public.att_holiday WHERE start_date BETWEEN ? AND ?;";
        return DB::connection('pgsql_biotime')->select($query, [$startDate, $endDate]);
    }
}