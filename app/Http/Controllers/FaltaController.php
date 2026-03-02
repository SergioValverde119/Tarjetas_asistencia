<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; 
use App\Services\TarjetaService;
use App\Repositories\TarjetaRepository;
use App\Exports\FaltasExport;
use Maatwebsite\Excel\Facades\Excel;
use Inertia\Inertia;
use Exception;

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
     * Muestra la interface de reportes con resultados filtrados.
     */
    public function index(Request $request)
    {
        // MAGIA: Le damos 5 minutos de vida a la petición para evitar que
        // la pantalla se quede en blanco o dé Error 500 al consultar todo el mes.
        set_time_limit(300); 

        try {
            $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->input('end_date', now()->format('Y-m-d'));
            $empId = $request->input('emp_id'); 
            $search = $request->input('search', ''); 

            $faltas = [];
            
            if ($request->has('start_date')) {
                $faltas = $this->obtenerFaltasProcesadas($empId, $startDate, $endDate);
            }

            return Inertia::render('Faltas/Index', [
                'faltas' => $faltas,
                'filters' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'emp_id' => $empId,
                    'search' => $search
                ],
                'empleados' => $this->tarjetaRepo->getAllEmployees()
            ]);
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Error en el monitor: ' . $e->getMessage());
        }
    }

    public function exportar(Request $request)
    {
        // También aplicamos la regla de tiempo para la exportación de Excel masiva
        set_time_limit(300); 

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $empId = $request->input('emp_id');

        $faltas = $this->obtenerFaltasProcesadas($empId, $startDate, $endDate);

        return Excel::download(
            new FaltasExport($faltas, $startDate, $endDate), 
            "Reporte_Faltas_{$startDate}_al_{$endDate}.xlsx"
        );
    }

    private function obtenerFaltasProcesadas($empId, $startDate, $endDate)
    {
        $listaFaltas = [];
        
        $query = DB::connection('pgsql_biotime')
            ->table('personnel_employee as e')
            ->select('e.id', 'e.emp_code', 'e.first_name', 'e.last_name')
            ->where('e.status', 0);

        if ($empId) {
            $query->where('e.id', $empId);
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