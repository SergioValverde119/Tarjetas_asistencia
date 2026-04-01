<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session; 
use App\Services\FaltaService;
use App\Repositories\FaltaRepository;
use App\Exports\FaltasExport;
use Maatwebsite\Excel\Facades\Excel;
use Inertia\Inertia;
use Exception;

/**
 * Controlador exclusivo para el Monitor de Faltas.
 * Gestiona la consulta de ausencias y exportación a Excel.
 * Primeramente Jehová Dios y Jesús Rey.
 */
class FaltaController extends Controller
{
    protected $faltaService;
    protected $faltaRepo;

    // Lista centralizada de nóminas a excluir (Administrativos/Especiales)
    protected $exclude = [
        '1206977','1020638', '1083527', '1162564', '994908', '927295', '121366',
        '124174', '19012781', '969638', '1155039', '159526', '1112581', '1170341',
        '43513', '208354', '825018', '212450', '186919', '806044', '871088',
        '181414', '183518', '127133', '203375', '1162441', '1107724', '1032472',
        '207122', '936674', '836809', '107981', '1124231', '11600013', '1203490', '19012824', '159587',
        '919436', '213547', '62215', '128436', '144146', '125022', '204627', '243062',
        '915174', '159564', '806015', '1189825', '159351', '213591', '161651', '802330', 
        '183018', '165558', '806076', '193016', '5297', '996009', '175627', '177368',
        '147829', '876747', '159537', '835827', '82675', '867078', '803941'
    ];

    public function __construct(FaltaService $faltaService, FaltaRepository $faltaRepo)
    {
        $this->faltaService = $faltaService;
        $this->faltaRepo = $faltaRepo;
    }

    public function index(Request $request)
    {
        set_time_limit(0); 

        try {
            $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->input('end_date', now()->format('Y-m-d'));
            $empId = $request->input('emp_id'); 
            $areaId = $request->input('area_id'); 
            $dateIncidence = $request->input('date_incidence');
            
            $faltas = [];

            // Solo se ejecuta la búsqueda si el usuario envía algún filtro
            if ($request->hasAny(['start_date', 'date_incidence', 'area_id', 'emp_id'])) {
                $finalStart = $dateIncidence ?: $startDate;
                $finalEnd = $dateIncidence ?: $endDate;

                // El cálculo de faltas sigue excluyendo a estas nóminas
                $faltas = $this->faltaService->procesarReporteFaltas(
                    $areaId, 
                    $empId, 
                    $finalStart, 
                    $finalEnd, 
                    $this->exclude
                );

                Session::put('faltas_actuales', $faltas);
                Session::put('filtros_actuales', ['start' => $finalStart, 'end' => $finalEnd]);
            }

            return Inertia::render('Faltas/Index', [
                'faltas' => $faltas,
                'filters' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'emp_id' => $empId,
                    'area_id' => $areaId,
                    'date_incidence' => $dateIncidence
                ],
                // MODIFICACIÓN: Ya no pasamos el filtro de exclusión aquí para que aparezcan en el buscador
                'empleados' => $this->faltaRepo->getAllEmployees(),
                'areas' => $this->faltaRepo->getAreas() 
            ]);

        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Error en el monitor: ' . $e->getMessage());
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