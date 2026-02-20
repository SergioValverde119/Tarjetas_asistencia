<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Repositories\KardexRepository;
use App\Services\KardexService; // <-- Importamos el Servicio

class EmpleadoController extends Controller
{
    protected $kardexRepo;
    protected $kardexService;

    // Inyectamos el Repositorio (para consultas simples) y el Servicio (para la lógica)
    public function __construct(KardexRepository $kardexRepo, KardexService $kardexService)
    {
        $this->kardexRepo = $kardexRepo;
        $this->kardexService = $kardexService;
    }

    public function show($id)
    {
        try {
            // 1. Buscar datos generales del empleado
            $empleado = DB::connection('pgsql_biotime')
                ->table('personnel_employee as e')
                ->leftJoin('personnel_department as d', 'e.department_id', '=', 'd.id')
                ->leftJoin('personnel_position as p', 'e.position_id', '=', 'p.id')
                ->select(
                    'e.id', 'e.emp_code', 'e.first_name', 'e.last_name', 
                    'e.hire_date', 'e.birthday', 'e.mobile', 'e.ssn', 'e.photo',
                    'e.email', 
                    'd.dept_name', 'p.position_name',
                    DB::raw("(
                        SELECT STRING_AGG(pa.area_name, ', ')
                        FROM public.personnel_employee_area pea
                        JOIN public.personnel_area pa ON pea.area_id = pa.id
                        WHERE pea.employee_id = e.id
                        AND pa.area_name != 'SEDUVI' 
                    ) as nomina")
                )
                ->where('e.id', $id)
                ->first();

            if (!$empleado) {
                abort(404, 'Empleado no encontrado');
            }

            // 2. Configurar Fechas
            $hoy = Carbon::now();
            $mes = request('mes') ? (int)request('mes') : $hoy->month;
            $ano = request('ano') ? (int)request('ano') : $hoy->year;
            
            $inicioMes = Carbon::createFromDate($ano, $mes, 1)->startOfDay();
            $finMes = Carbon::createFromDate($ano, $mes, 1)->endOfMonth()->endOfDay();
            
            // 3. Obtener el Kárdex Procesado USANDO EL SERVICIO
            $stats = $this->kardexService->obtenerReporteMensualEmpleado($empleado, $mes, $ano);

            // --- 4. CALENDARIO VISUAL ---
            $calendario = [];
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
                    'incidencia' => $stats['incidencias_diarias'][$day] ?? '',
                    'isToday' => $fechaDia->isToday(),
                    'diaSemana' => $fechaDia->dayOfWeek 
                ];
            }
            
            // 5. Datos Extra de Catálogos (usando el repositorio)
            $horario = $this->kardexRepo->getHorarioActual($empleado->id);
            $catalogoPermisos = $this->kardexRepo->getCatalogoPermisos();

            $fechaTitulo = Carbon::createFromDate($ano, $mes, 1)->isoFormat('MMMM YYYY');

            return Inertia::render('Empleado/Show', [
                'empleado' => $empleado,
                'stats' => $stats,
                'fechaActual' => ucfirst($fechaTitulo),
                'calendario' => $calendario,
                'filtros' => ['mes' => $mes, 'ano' => $ano],
                'horario' => $horario,
                'catalogoPermisos' => $catalogoPermisos,
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}