<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

/**
 * Controlador para la Gestión de Listas de Asistencia.
 * Sincronizado con la estructura real de att_attschedule de BioTime.
 * Primeramente Jehová Dios y Jesús Rey.
 */
class ListaAsistenciaController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $employeeId = $request->input('employee_id');
        $month = (int) $request->input('mes', date('n'));
        $year = (int) $request->input('ano', date('Y'));

        // 1. Buscador de empleados
        $employees = DB::connection('pgsql_biotime')
            ->table('personnel_employee as e')
            ->join('personnel_department as d', 'e.department_id', '=', 'd.id')
            ->select('e.id', 'e.emp_code', 'e.first_name', 'e.last_name', 'd.dept_name as department_name')
            ->when($search, function($query, $search) {
                $query->where(function($q) use ($search) {
                    $q->where('e.first_name', 'ilike', "%{$search}%")
                      ->orWhere('e.last_name', 'ilike', "%{$search}%")
                      ->orWhere('e.emp_code', 'like', "%{$search}%");
                });
            })
            ->limit(10)
            ->get();

        $selectedEmployee = null;
        $attendanceData = null;

        if ($employeeId) {
            $selectedEmployee = DB::connection('pgsql_biotime')
                ->table('personnel_employee as e')
                ->join('personnel_department as d', 'e.department_id', '=', 'd.id')
                ->where('e.id', $employeeId)
                ->select('e.id', 'e.emp_code', 'e.first_name', 'e.last_name', 'd.dept_name as department_name')
                ->first();

            if ($selectedEmployee) {
                // --- LÓGICA DE HORARIOS SEGÚN TU REPOSITORIO ---
                
                // 1. Intentar buscar asignación individual (att_attschedule)
                $asignacion = DB::connection('pgsql_biotime')
                    ->table('att_attschedule as asch')
                    ->join('att_attshift as s', 'asch.shift_id', '=', 's.id')
                    ->where('asch.employee_id', $employeeId)
                    ->whereRaw('CURRENT_DATE BETWEEN asch.start_date AND asch.end_date')
                    ->select('s.id')
                    ->first();

                // 2. Si no hay individual, buscar por departamento (att_departmentschedule)
                if (!$asignacion) {
                    $asignacion = DB::connection('pgsql_biotime')
                        ->table('personnel_employee as e')
                        ->join('att_departmentschedule as dsch', 'e.department_id', '=', 'dsch.department_id')
                        ->join('att_attshift as s', 'dsch.shift_id', '=', 's.id')
                        ->where('e.id', $employeeId)
                        ->select('s.id')
                        ->first();
                }

                $displaySchedule = "SIN HORARIO ASIGNADO";

                if ($asignacion) {
                    // Obtener el primer intervalo disponible del turno
                    $intervalo = DB::connection('pgsql_biotime')
                        ->table('att_shiftdetail as sd')
                        ->join('att_timeinterval as ti', 'sd.time_interval_id', '=', 'ti.id')
                        ->where('sd.shift_id', $asignacion->id)
                        ->select(
                            'ti.in_time', 
                            DB::raw("(ti.in_time::time + (COALESCE(ti.work_time_duration, 0) || ' minutes')::interval)::time as out_time")
                        )
                        ->first();

                    if ($intervalo) {
                        $in = substr($intervalo->in_time, 0, 5);
                        $out = substr($intervalo->out_time, 0, 5);
                        $displaySchedule = "{$in} A {$out}  HRS";
                    }
                }

                // Fines de semana
                $weekends = [];
                $date = Carbon::createFromDate($year, $month, 1);
                for ($d = 1; $d <= $date->daysInMonth; $d++) {
                    $curr = Carbon::createFromDate($year, $month, $d);
                    if ($curr->isSaturday()) $weekends[] = ['day' => $d, 'label' => 'SÁBADO'];
                    elseif ($curr->isSunday()) $weekends[] = ['day' => $d, 'label' => 'DOMINGO'];
                }

                // FERIADOS (Columna 'alias')
                $holidays = DB::connection('pgsql_biotime')
                    ->table('att_holiday')
                    ->whereYear('start_date', $year)
                    ->whereMonth('start_date', $month)
                    ->select(DB::raw("EXTRACT(DAY FROM start_date) as day"), 'alias as name')
                    ->get()->toArray();

                // JUSTIFICACIONES (Motivos reales)
                $justifications = DB::connection('pgsql_biotime')
                    ->table('att_leave as l')
                    ->join('att_leavecategory as c', 'l.category_id', '=', 'c.id')
                    ->where('l.employee_id', $employeeId)
                    ->whereYear('l.start_time', $year)
                    ->whereMonth('l.start_time', $month)
                    ->select(
                        DB::raw("EXTRACT(DAY FROM l.start_time) as day"),
                        DB::raw("COALESCE(NULLIF(l.apply_reason, ''), c.category_name) as motivo")
                    )
                    ->get();

                $attendanceData = [
                    'schedule' => $displaySchedule,
                    'weekends' => $weekends,
                    'holidays' => $holidays,
                    'justifications' => $justifications
                ];
            }
        }

        return Inertia::render('Lista_Asistencia/Index', [
            'employees' => $employees,
            'selectedEmployee' => $selectedEmployee,
            'attendanceData' => $attendanceData,
            'filters' => $request->only(['search', 'employee_id', 'mes', 'ano']),
            'months' => ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"]
        ]);
    }
}