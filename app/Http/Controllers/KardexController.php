<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Repositories\KardexRepository;
use App\Exports\KardexExport;
use Maatwebsite\Excel\Facades\Excel;

class KardexController extends Controller
{
    protected $kardexRepo;

    public function __construct(KardexRepository $kardexRepo)
    {
        $this->kardexRepo = $kardexRepo;
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
            // --- NUEVO FILTRO ---
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
                // --- NUEVO FILTRO ---
                'sin_horario' => 'nullable|boolean',
            ]);
            
            $mesNombre = Carbon::create()->month((int)$filtros['mes'])->monthName;
            $fileName = sprintf('Kardex_%s_%s_Q%s.xlsx', (int)$filtros['ano'], $mesNombre, (int)$filtros['quincena']);

            return Excel::download(new KardexExport($filtros, $this->kardexRepo), $fileName);

        } catch (\Throwable $e) {
            report($e);
            return response('Error al generar el export. Revise el log del sistema.', 500);
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
            // --- NUEVO FILTRO ---
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

        $fechaInicioMes = Carbon::createFromDate($filtros['ano'], $filtros['mes'], 1)->startOfDay();
        $fechaFinMes = Carbon::createFromDate($filtros['ano'], $filtros['mes'], 1)->endOfMonth()->endOfDay();
        
        $listaNominas = $this->kardexRepo->getNominas();
        $catalogoPermisos = $this->kardexRepo->getCatalogoPermisos();

        $empleadosPaginados = $this->kardexRepo->getEmpleadosPaginados($filtros);
        $empleadoIDs = $empleadosPaginados->pluck('id')->toArray();

        $payloadData = collect();
        $permisos = collect();
        
        if (count($empleadoIDs) > 0) {
            $payloadData = $this->kardexRepo->getPayloadData($empleadoIDs, $fechaInicioMes, $fechaFinMes);
            $permisos = $this->kardexRepo->getPermisos($empleadoIDs, $fechaInicioMes, $fechaFinMes);
        }

        $datosKardex = $this->kardexRepo->procesarKardex(
            $empleadosPaginados->items(), 
            $payloadData,
            $permisos, 
            $filtros['mes'], $filtros['ano'], $diaInicio, $diaFin
        );
        
        return Inertia::render('Kardex/Index', [
            'datosKardex' => $datosKardex, 
            'paginador' => $empleadosPaginados, 
            'rangoDeDias' => $rangoDeDias,
            'listaNominas' => $listaNominas,
            'catalogoPermisos' => $catalogoPermisos,
            'filtros' => [
                'mes' => $filtros['mes'],
                'ano' => $filtros['ano'],
                'quincena' => $filtros['quincena'],
                'perPage' => $filtros['perPage'],
                'search' => $filtros['search'] ?? '',
                'nomina' => $filtros['nomina'],
                // --- PASAMOS EL ESTADO AL VUE ---
                'sin_horario' => $filtros['sin_horario'],
            ]
        ]);
    }
}