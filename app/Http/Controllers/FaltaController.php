<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session; 
use App\Services\FaltaService;
use App\Repositories\FaltaRepository;
use App\Models\ExclusionFalta;
use App\Exports\FaltasExport;
use Maatwebsite\Excel\Facades\Excel;
use Inertia\Inertia;
use Throwable; 
use Illuminate\Support\Facades\Log;

/**
 * Controlador para el Monitor de Faltas.
 * Optimizado para procesos pesados y grandes volúmenes de datos.
 * Primeramente Jehová Dios y Jesús Rey.
 */
class FaltaController extends Controller
{
    protected $faltaService;
    protected $faltaRepo;

    public function __construct(FaltaService $faltaService, FaltaRepository $faltaRepo)
    {
        $this->faltaService = $faltaService;
        $this->faltaRepo = $faltaRepo;
    }

    public function index(Request $request)
    {
        // --- CONFIGURACIÓN PARA PROCESOS MASIVOS ---
        // Forzamos al máximo los límites del servidor para evitar el error de los 30 segundos
        ini_set('max_execution_time', '0'); // Tiempo ilimitado
        ini_set('memory_limit', '1024M');    // 1GB de RAM para el proceso
        set_time_limit(0); 

        try {
            $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->input('end_date', now()->format('Y-m-d'));
            $empId = $request->filled('emp_id') ? $request->input('emp_id') : null;
            $dateIncidence = $request->filled('date_incidence') ? $request->input('date_incidence') : null;
            $areaId = null; 
            
            $faltas = [];

            // 1. Obtención segura de exclusiones
            try {
                $excludeList = ExclusionFalta::getListaCodigos();
            } catch (Throwable $dbError) {
                Log::warning("Exclusiones no cargadas: " . $dbError->getMessage());
                $excludeList = []; 
            }

            // 2. Ejecución de la búsqueda
            if ($request->hasAny(['start_date', 'date_incidence', 'emp_id'])) {
                $finalStart = $dateIncidence ?: $startDate;
                $finalEnd = $dateIncidence ?: $endDate;

                // El servicio ejecuta el loop de empleados. 
                // Si son muchos, aquí es donde se consumía el tiempo.
                $faltas = $this->faltaService->procesarReporteFaltas(
                    null, 
                    $empId, 
                    $finalStart, 
                    $finalEnd, 
                    $excludeList
                );

                Session::put('faltas_actuales', $faltas);
                Session::put('filtros_actuales', ['start' => $finalStart, 'end' => $finalEnd]);
            }

            // 3. Retorno a la vista
            return Inertia::render('Faltas/Index', [
                'faltas' => $faltas,
                'filters' => [
                    'start_date'     => $startDate,
                    'end_date'       => $endDate,
                    'emp_id'         => $empId,
                    'area_id'        => $areaId,
                    'date_incidence' => $dateIncidence
                ],
                // Nota: getAllEmployees también puede ser pesado si hay miles de empleados.
                'empleados' => $this->faltaRepo->getAllEmployees(),
            ]);

        } catch (Throwable $e) {
            Log::error("Fallo crítico en Monitor de Faltas: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return redirect()->back()->with('error', 'El servidor tardó demasiado o se agotó la memoria. Intente filtrar por un rango de fechas más corto.');
        }
    }

    public function exportar()
    {
        $faltas = Session::get('faltas_actuales', []);
        $info = Session::get('filtros_actuales', ['start' => 'N/A', 'end' => 'N/A']);

        if (empty($faltas)) {
            return redirect()->back()->with('error', 'No hay datos para exportar.');
        }

        return Excel::download(
            new FaltasExport($faltas, $info['start'], $info['end']), 
            "Reporte_Faltas_" . now()->format('Ymd_His') . ".xlsx"
        );
    }
}