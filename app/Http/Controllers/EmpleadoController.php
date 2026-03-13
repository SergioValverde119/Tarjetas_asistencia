<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Repositories\KardexRepository;
use App\Services\KardexService; 

/**
 * Controlador de Empleado
 * Sincronizado con la vista Empleado/Show.vue
 * Primeramente Jehová Dios y Jesús Rey.
 */
class EmpleadoController extends Controller
{
    protected $kardexRepo;
    protected $kardexService;

    public function __construct(KardexRepository $kardexRepo, KardexService $kardexService)
    {
        $this->kardexRepo = $kardexRepo;
        $this->kardexService = $kardexService;
    }

    /**
     * Muestra el detalle individual del kárdex para un empleado.
     */
    public function show(Request $request, $id)
    {
        $hoy = Carbon::now();
        $mes = $request->input('mes') ? (int)$request->input('mes') : $hoy->month;
        $ano = $request->input('ano') ? (int)$request->input('ano') : $hoy->year;

        try {
            // 1. Buscamos datos generales del empleado
            $empleado = DB::connection('pgsql_biotime')
                ->table('personnel_employee as e')
                ->leftJoin('personnel_department as d', 'e.department_id', '=', 'd.id')
                ->leftJoin('personnel_position as p', 'e.position_id', '=', 'p.id')
                ->select(
                    'e.id', 'e.emp_code', 'e.first_name', 'e.last_name', 
                    'e.hire_date', 'e.birthday', 'e.mobile', 'e.ssn', 'e.photo',
                    'e.email', 'd.dept_name', 'p.position_name',
                    DB::raw("(SELECT STRING_AGG(pa.area_name, ', ') FROM public.personnel_employee_area pea JOIN public.personnel_area pa ON pea.area_id = pa.id WHERE pea.employee_id = e.id AND pa.area_name != 'SEDUVI') as nomina")
                )
                ->where('e.id', $id)
                ->first();

            if (!$empleado) {
                return redirect()->route('kardex.index')->with('error', 'Empleado no encontrado.');
            }

            // 2. Obtener estadísticas del servicio
            $datosProcesados = $this->kardexService->obtenerReporteMensualEmpleado($empleado, $mes, $ano);

            // --- MAPEO DE TOTALES PARA LA VISTA ---
            // La vista espera: total_faltas, total_rg, total_rl, total_permisos, total_omisiones
            $stats = (object)[
                'total_faltas' => $datosProcesados['total_f'],
                'total_rg' => $datosProcesados['total_rg'],
                'total_rl' => $datosProcesados['total_rl'],
                'total_permisos' => $datosProcesados['total_j'],
                'total_omisiones' => $datosProcesados['total_omisiones'],
                'incidencias_diarias' => $datosProcesados['incidencias_diarias']
            ];

            // 3. Construir calendario para la vista de lista
            $inicioMes = Carbon::createFromDate($ano, $mes, 1)->startOfDay();
            $finMes = $inicioMes->copy()->endOfMonth();
            $calendario = [];

            for ($day = 1; $day <= $finMes->day; $day++) {
                $fechaDia = Carbon::createFromDate($ano, $mes, $day);
                $calendario[] = [
                    'type' => 'day',
                    'day' => $day,
                    'nombre_dia' => $fechaDia->isoFormat('ddd'),
                    'incidencia' => $stats->incidencias_diarias[$day] ?? null,
                    'isToday' => $fechaDia->isToday()
                ];
            }

            // 4. Obtener horario detallado (Nombre + Días)
            $horarioBase = $this->kardexRepo->getHorarioActualConDias($empleado->id);

            return Inertia::render('Empleado/Show', [
                'empleado' => $empleado,
                'stats' => $stats,
                'calendario' => $calendario,
                'catalogoPermisos' => $this->kardexRepo->getCatalogoPermisos(),
                'fechaActual' => ucfirst($inicioMes->isoFormat('MMMM YYYY')),
                'filtros' => ['mes' => $mes, 'ano' => $ano],
                'horario' => $horarioBase
            ]);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }
}