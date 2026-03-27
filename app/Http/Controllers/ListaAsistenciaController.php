<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

/**
 * Controlador para la Gestión de Listas de Asistencia.
 * Corregido: Nombre de columna en att_holiday (start_date).
 * Primeramente Jehová Dios y Jesús Rey.
 */
class ListaAsistenciaController extends Controller
{
    /**
     * Pantalla de selección de empleado y periodo.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $employees = DB::connection('pgsql_biotime')
            ->table('personnel_employee as e')
            ->join('personnel_department as d', 'e.department_id', '=', 'd.id')
            ->select(
                'e.id',
                'e.emp_code',
                'e.first_name',
                'e.last_name',
                'd.dept_name as department_name'
            )
            ->when($search, function($query, $search) {
                $query->where(function($q) use ($search) {
                    $q->where('e.first_name', 'ilike', "%{$search}%")
                      ->orWhere('e.last_name', 'ilike', "%{$search}%")
                      ->orWhere('e.emp_code', 'like', "%{$search}%");
                });
            })
            ->limit(10)
            ->get();

        // Asegúrate de que la carpeta sea resources/js/Pages/Lista_Asistencia/Index.vue
        return Inertia::render('Lista_Asistencia/Index', [
            'employees' => $employees,
            'filters' => $request->only(['search']),
            'months' => [
                "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
                "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
            ]
        ]);
    }

    /**
     * Genera la vista del PDF de la lista de asistencia.
     */
    public function show(Request $request, $id)
    {
        $month = $request->input('mes', date('n'));
        $year = $request->input('ano', date('Y'));
        
        $employee = DB::connection('pgsql_biotime')
            ->table('personnel_employee as e')
            ->join('personnel_department as d', 'e.department_id', '=', 'd.id')
            ->where('e.id', $id)
            ->select('e.id', 'e.emp_code', 'e.first_name', 'e.last_name', 'd.dept_name as department_name')
            ->first();

        if (!$employee) return redirect()->route('asistencia.index')->with('error', 'Empleado no encontrado.');

        // Horario (Lógica de ejemplo)
        $schedule = "08:00 - 16:00"; 

        // Justificaciones (att_leave suele usar start_time)
        $justifications = DB::connection('pgsql_biotime')
            ->table('att_leave as l')
            ->join('att_leavecategory as c', 'l.category_id', '=', 'c.id')
            ->where('l.employee_id', $id)
            ->whereYear('l.start_time', $year)
            ->whereMonth('l.start_time', $month)
            ->select(DB::raw("EXTRACT(DAY FROM l.start_time) as day"), 'c.category_name as motivo')
            ->get();

        // --- CORRECCIÓN: Se cambió start_time por start_date para att_holiday ---
        $holidays = DB::connection('pgsql_biotime')
            ->table('att_holiday')
            ->whereYear('start_date', $year)
            ->whereMonth('start_date', $month)
            ->select(DB::raw("EXTRACT(DAY FROM start_date) as day"))
            ->pluck('day')->toArray();

        // Fines de semana
        $weekends = [];
        $date = Carbon::createFromDate($year, $month, 1);
        for ($d = 1; $d <= $date->daysInMonth; $d++) {
            $curr = Carbon::createFromDate($year, $month, $d);
            if ($curr->isSaturday()) $weekends[] = ['day' => $d, 'label' => 'SÁBADO'];
            elseif ($curr->isSunday()) $weekends[] = ['day' => $d, 'label' => 'DOMINGO'];
        }

        // Asegúrate de que la carpeta sea resources/js/Pages/Lista_Asistencia/Pdf.vue
        return Inertia::render('Lista_Asistencia/Pdf', [
            'employee' => $employee,
            'selectedMonth' => (int)$month,
            'selectedYear' => (int)$year,
            'schedule' => $schedule,
            'justifications' => $justifications,
            'holidays' => $holidays,
            'weekends' => $weekends,
            'months' => ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"]
        ]);
    }
}