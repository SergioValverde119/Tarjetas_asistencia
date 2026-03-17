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
 * Sincronizado para que los nombres coincidan con la vista Vue (Show.vue).
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

            // 2. Obtener estadísticas del servicio (Motor unificado)
            $datosProcesados = $this->kardexService->obtenerReporteMensualEmpleado($empleado, $mes, $ano);

            // --- CORRECCIÓN: Nombres sincronizados con el frontend ---
            $stats = (object)[
                'total_f' => $datosProcesados['total_f'] ?? 0,
                'total_rg' => $datosProcesados['total_rg'] ?? 0,
                'total_rl' => $datosProcesados['total_rl'] ?? 0,
                'total_j' => $datosProcesados['total_j'] ?? 0,
                'total_omisiones' => $datosProcesados['total_omisiones'] ?? 0,
                'incidencias_diarias' => $datosProcesados['incidencias_diarias'] ?? []
            ];

            // 3. Construir calendario para la cuadrícula visual
            $inicioMes = Carbon::createFromDate($ano, $mes, 1)->startOfDay();
            $finMes = $inicioMes->copy()->endOfMonth();
            $calendario = [];

            // Relleno de días vacíos para alinear el calendario (Lun-Dom)
            $primerDiaSemana = $inicioMes->dayOfWeek; 
            for ($i = 0; $i < $primerDiaSemana; $i++) {
                $calendario[] = ['type' => 'empty', 'id' => "empty-$i"];
            }

            for ($day = 1; $day <= $finMes->day; $day++) {
                $fechaDia = Carbon::createFromDate($ano, $mes, $day);
                $calendario[] = [
                    'type' => 'day',
                    'id' => $day,
                    'day' => $day,
                    'nombre_dia' => $fechaDia->isoFormat('ddd'),
                    'incidencia' => $stats->incidencias_diarias[$day] ?? null,
                    'isToday' => $fechaDia->isToday()
                ];
            }

            // 4. Otros datos de apoyo
            $catalogoPermisos = $this->kardexRepo->getCatalogoPermisos();
            $horarioBase = $this->kardexRepo->getHorarioActualConDias($empleado->id);

            return Inertia::render('Empleado/Show', [
                'empleado' => $empleado,
                'stats' => $stats,
                'calendario' => $calendario,
                'catalogoPermisos' => $catalogoPermisos,
                'fechaActual' => ucfirst($inicioMes->isoFormat('MMMM YYYY')),
                'filtros' => ['mes' => $mes, 'ano' => $ano],
                'horario' => $horarioBase
            ]);

        } catch (\Exception $e) {
            report($e);
            return redirect()->back()->with('error', 'Error al cargar el detalle: ' . $e->getMessage());
        }
    }
}