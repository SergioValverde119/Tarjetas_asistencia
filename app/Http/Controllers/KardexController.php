<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Services\KardexService; 
use App\Repositories\KardexRepository;
use App\Exports\KardexExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

/**
 * Controlador de Kárdex Principal
 * Corregido: Se restauró el nombre de clase KardexController para evitar conflictos.
 * Primeramente Jehová Dios y Jesús Rey.
 */
class KardexController extends Controller
{
    protected $kardexService;
    protected $kardexRepo;

    public function __construct(KardexService $kardexService, KardexRepository $kardexRepo)
    {
        $this->kardexService = $kardexService;
        $this->kardexRepo = $kardexRepo;
    }

    /**
     * Muestra la tabla general del kárdex (Index).
     */
    public function index(Request $request)
    {
        return $this->mostrarVista($request);
    }
    
    /**
     * Procesa los filtros y redirecciona.
     */
    public function buscar(Request $request)
    {
        $validatedData = $request->validate([
            'mes' => 'required|integer|min:1|max:12',
            'ano' => 'required|integer|min:2020|max:2030',
            'quincena' => 'required|integer|min:0|max:2',
            'perPage' => 'required|integer|in:10,20,50,200',
            'search' => 'nullable|string|max:50',
            'nomina' => 'nullable|integer',
            'sin_horario' => 'nullable|boolean',
        ]);
        
        return redirect()->route('kardex.index', $validatedData);
    }

    /**
     * Exportación a Excel utilizando el motor optimizado.
     */
    public function exportar(Request $request)
    {
        try {
            $filtros = $request->validate([
                'mes' => 'required|integer|min:1|max:12',
                'ano' => 'required|integer|min:2020|max:2030',
                'quincena' => 'required|integer|min:0|max:2',
                'perPage' => 'nullable|integer',
                'search' => 'nullable|string|max:50',
                'nomina' => 'nullable|integer',
                'sin_horario' => 'nullable|boolean',
            ]);
            
            $mesNombre = Carbon::create()->month((int)$filtros['mes'])->monthName;
            $fileName = sprintf('Kardex_%s_%s_Q%s.xlsx', (int)$filtros['ano'], $mesNombre, (int)$filtros['quincena']);

            return Excel::download(new KardexExport($filtros, $this->kardexService), $fileName);

        } catch (\Throwable $e) {
            Log::error('Error al generar el export: ' . $e->getMessage());
            return response('Error al generar el export: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lógica interna para preparar la vista general.
     */
    private function mostrarVista(Request $request)
    {
        $filtros = [
            'mes' => (int)$request->input('mes', date('m')),
            'ano' => (int)$request->input('ano', date('Y')),
            'quincena' => (int)$request->input('quincena', 0),
            'perPage' => (int)$request->input('perPage', 10),
            'search' => $request->input('search'),
            'nomina' => $request->input('nomina') ? (int)$request->input('nomina') : null,
            'sin_horario' => filter_var($request->input('sin_horario'), FILTER_VALIDATE_BOOLEAN),
        ];
        
        $fechaBase = Carbon::createFromDate($filtros['ano'], $filtros['mes'], 1);
        $diasTotalesDelMes = $fechaBase->daysInMonth;
        $diaInicio = ($filtros['quincena'] == 2) ? 16 : 1;
        $diaFin = ($filtros['quincena'] == 1) ? 15 : $diasTotalesDelMes;
        
        $nombresDias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        $rangoDeDias = [];

        for ($d = $diaInicio; $d <= $diaFin; $d++) {
            $fecha = Carbon::createFromDate($filtros['ano'], $filtros['mes'], $d);
            $rangoDeDias[] = [
                'num' => $d,
                'nombre' => $nombresDias[$fecha->dayOfWeek], 
                'esFin' => $fecha->isWeekend()
            ];
        }

        // Obtener data procesada mediante el servicio (Motor de Tarjetas)
        $dataServicio = $this->kardexService->generarKardex($filtros);
        
        return Inertia::render('Kardex/Index', [
            'datosKardex' => $dataServicio['datosKardex'], 
            'paginador' => $dataServicio['paginador'], 
            'rangoDeDias' => $rangoDeDias,
            'listaNominas' => $dataServicio['listaNominas'],
            'catalogoPermisos' => $dataServicio['catalogoPermisos'],
            'filtros' => $filtros
        ]);
    }
}