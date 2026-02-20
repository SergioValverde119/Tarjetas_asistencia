<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Services\KardexService; // Importamos el Servicio
use App\Exports\KardexExport;
use Maatwebsite\Excel\Facades\Excel;

class KardexController extends Controller
{
    protected $kardexService;

    // Inyección de Dependencia del Servicio
    public function __construct(KardexService $kardexService)
    {
        $this->kardexService = $kardexService;
    }

    public function index(Request $request)
    {
        return $this->mostrarVista($request);
    }
    
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

            // CAMBIO CRÍTICO: Pasamos el Servicio ($this->kardexService)
            // Ya no instanciamos el repositorio manualmente.
            return Excel::download(new KardexExport($filtros, $this->kardexService), $fileName);

        } catch (\Throwable $e) {
            report($e);
            return response('Error al generar el export: ' . $e->getMessage(), 500);
        }
    }

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
        
        // Estructura visual de días
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

        // Llamada al Servicio para obtener datos
        $dataServicio = $this->kardexService->generarKardex($filtros);
        
        return Inertia::render('Kardex/Index', [
            'datosKardex' => $dataServicio['datosKardex'], 
            'paginador' => $dataServicio['paginador'], 
            'rangoDeDias' => $rangoDeDias,
            'listaNominas' => $dataServicio['listaNominas'],
            'catalogoPermisos' => $dataServicio['catalogoPermisos'],
            'filtros' => [
                'mes' => $filtros['mes'],
                'ano' => $filtros['ano'],
                'quincena' => $filtros['quincena'],
                'perPage' => $filtros['perPage'],
                'search' => $filtros['search'] ?? '',
                'nomina' => $filtros['nomina'],
                'sin_horario' => $filtros['sin_horario'],
            ]
        ]);
    }
}