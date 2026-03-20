<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; 
use Illuminate\Support\Facades\Session; 
use App\Services\TarjetaService;
use App\Repositories\TarjetaRepository;
use App\Exports\FaltasExport;
use Maatwebsite\Excel\Facades\Excel;
use Inertia\Inertia;
use Exception;

/**
 * Monitor de Faltas con Procesamiento Único y Filtro de Exclusión
 * Primeramente Jehová Dios y Jesús Rey.
 */
class FaltaController extends Controller
{
    protected $tarjetaService;
    protected $tarjetaRepo;

    public function __construct(TarjetaService $tarjetaService, TarjetaRepository $tarjetaRepo)
    {
        $this->tarjetaService = $tarjetaService;
        $this->tarjetaRepo = $tarjetaRepo;
    }

    /**
     * Muestra la interfaz y PROCESA los datos para dejarlos listos.
     */
    public function index(Request $request)
    {
        // Ampliamos el tiempo para el cálculo inicial pesado
        set_time_limit(300); 

        try {
            $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->input('end_date', now()->format('Y-m-d'));
            $empId = $request->input('emp_id'); 
            $search = $request->input('search', ''); 
            $dateIncidence = $request->input('date_incidence', '');
            
           
            $exclude = [
            '1206977',
            '1020638',
'1083527',
'1162564',
'994908',
'927295',
'121366',
'124174',
'19012781',
'969638',
'1155039',
'159526',
'1112581',
'1170341',
'43513',
'208354',
'825018',
'212450',
'186919',
'806044',
'871088',
'181414',
'183518',
'127133',
'203375',
'1162441',
'1107724',
'1032472',
'207122',
'936674',
'836809',
'107981',
'1124231',
'11600013',
'1203490',
'19012824']; 

            $faltas = [];
            
            // Si hay parámetros de búsqueda, calculamos
            if ($request->has('start_date') || $request->has('date_incidence')) {
                
                $finalStart = $dateIncidence ?: $startDate;
                $finalEnd = $dateIncidence ?: $endDate;

                // 1. CALCULAMOS UNA SOLA VEZ (Pasando la lista de exclusión)
                $faltas = $this->obtenerFaltasProcesadas($empId, $finalStart, $finalEnd, $exclude);

                // 2. GUARDAMOS EN SESIÓN (El almacén para el Excel)
                // Esto garantiza que el Excel sea idéntico a lo que ves en pantalla
                Session::put('faltas_actuales', $faltas);
                Session::put('filtros_actuales', [
                    'start' => $finalStart,
                    'end' => $finalEnd
                ]);
            }

            return Inertia::render('Faltas/Index', [
                'faltas' => $faltas,
                'filters' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'emp_id' => $empId,
                    'search' => $search,
                    'date_incidence' => $dateIncidence,
                    'exclude' => $exclude
                ],
                'empleados' => $this->tarjetaRepo->getAllEmployees()
            ]);
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Error en el monitor: ' . $e->getMessage());
        }
    }

    /**
     * EXPORTAR: Descarga instantánea de lo que ya está en la sesión (ya filtrado).
     */
    public function exportar()
    {
        // Recuperamos los datos que el método 'index' ya procesó y filtró
        $faltas = Session::get('faltas_actuales', []);
        $info = Session::get('filtros_actuales', [
            'start' => 'N/A', 
            'end' => 'N/A'
        ]);

        if (empty($faltas)) {
            return redirect()->back()->with('error', 'No hay datos cargados para exportar. Realice una búsqueda primero.');
        }

        return Excel::download(
            new FaltasExport($faltas, $info['start'], $info['end']), 
            "Reporte_Faltas_" . now()->format('Ymd_His') . ".xlsx"
        );
    }

    /**
     * Motor de procesamiento (Cálculo real contra BioTime con exclusión)
     */
    private function obtenerFaltasProcesadas($empId, $startDate, $endDate, $exclude = [])
    {
        $listaFaltas = [];
        
        $query = DB::connection('pgsql_biotime')
            ->table('personnel_employee as e')
            ->select('e.id', 'e.emp_code', 'e.first_name', 'e.last_name')
            ->where('e.status', 0);

        if ($empId) {
            $query->where('e.id', $empId);
        }

        // --- APLICACIÓN DEL FILTRO DE NÓMINAS ---
        if (!empty($exclude)) {
            $query->whereNotIn('e.emp_code', (array)$exclude);
        }

        // Bloqueo de empleados sin horario asignado
        $query->whereExists(function ($sub) use ($startDate, $endDate) {
            $sub->select(DB::raw(1))
                ->from('public.att_attschedule as s')
                ->whereColumn('s.employee_id', 'e.id')
                ->where('s.end_date', '>=', $startDate)
                ->where('s.start_date', '<=', $endDate);
        });

        $empleados = $query->get();

        foreach ($empleados as $emp) {
            $asistencia = $this->tarjetaService->obtenerDatosPorRango($emp->id, $startDate, $endDate);
            
            foreach ($asistencia as $dia) {
                if ($dia['calificacion'] === 'F' && $dia['observaciones'] !== 'Sin horario asignado') {
                    $listaFaltas[] = [
                        'nomina' => $emp->emp_code,
                        'nombre' => $emp->first_name . ' ' . $emp->last_name,
                        'fecha' => $dia['dia'],
                        'checkin' => $dia['checkin'] ?? null,
                        'checkout' => $dia['checkout'] ?? null,
                        'horario' => $dia['horario_nombre'] ?? 'Sin horario asignado',
                        'observaciones' => $dia['observaciones']
                    ];
                }
            }
        }

        return $listaFaltas;
    }
}