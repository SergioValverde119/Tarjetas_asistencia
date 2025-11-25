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

    /**
     * Muestra la página de visualización web del Kardex.
     */
    public function index(Request $request)
    {
        return $this->mostrarVista($request);
    }
    
    /**
     * Recibe los filtros del formulario y redirige a index con los parámetros.
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
        ]);
        
        return redirect()->route('kardex.index', $validatedData);
    }

    /**
     * Genera y descarga el archivo Excel.
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
            ]);
            
            // Generamos el nombre del archivo
            $mesNombre = Carbon::create()->month((int)$filtros['mes'])->monthName;
            $fileName = sprintf('Kardex_%s_%s_Q%s.xlsx', (int)$filtros['ano'], $mesNombre, (int)$filtros['quincena']);

            // Descargamos el Excel usando la clase Export y el Repositorio
            return Excel::download(new KardexExport($filtros, $this->kardexRepo), $fileName);

        } catch (\Throwable $e) {
            // Si falla la exportación, registramos el error pero no rompemos la página visible
            report($e);
            return response('Error al generar el export. Por favor intente de nuevo.', 500);
        }
    }

    /**
     * Lógica central para preparar los datos de la vista.
     */
    private function mostrarVista(Request $request)
    {
        // 1. Preparar Filtros
        $filtros = [
            'mes' => (int)$request->input('mes', date('m')),
            'ano' => (int)$request->input('ano', date('Y')),
            'quincena' => (int)$request->input('quincena', 0),
            'perPage' => (int)$request->input('perPage', 10),
            'search' => $request->input('search'),
            'nomina' => $request->input('nomina') ? (int)$request->input('nomina') : null,
        ];
        
        // 2. Calcular Fechas
        $fechaBase = Carbon::createFromDate($filtros['ano'], $filtros['mes'], 1);
        $diasTotalesDelMes = $fechaBase->daysInMonth;
        $diaInicio = ($filtros['quincena'] == 2) ? 16 : 1;
        $diaFin = ($filtros['quincena'] == 1) ? 15 : $diasTotalesDelMes;
        
        // Generamos el array de días con nombre (Lun, Mar) para la cabecera de la tabla
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
        
        // 3. Consultas a Base de Datos (Vía Repositorio)
        
        // Lista de nóminas para el dropdown
        $listaNominas = $this->kardexRepo->getNominas();

        // Empleados paginados
        $empleadosPaginados = $this->kardexRepo->getEmpleadosPaginados($filtros);
        $empleadoIDs = $empleadosPaginados->pluck('id')->toArray();

        // Detalles (Asistencias y Permisos)
        $payloadData = collect();
        $permisos = collect();
        
        if (count($empleadoIDs) > 0) {
            $payloadData = $this->kardexRepo->getPayloadData($empleadoIDs, $fechaInicioMes, $fechaFinMes);
            $permisos = $this->kardexRepo->getPermisos($empleadoIDs, $fechaInicioMes, $fechaFinMes);
        }

        // 4. Procesamiento de Lógica de Negocio (Cruce de datos)
        $datosKardex = $this->kardexRepo->procesarKardex(
            $empleadosPaginados->items(), 
            $payloadData,
            $permisos, 
            $filtros['mes'], $filtros['ano'], $diaInicio, $diaFin
        );
        
        // 5. Renderizado
        return Inertia::render('Kardex/Index', [
            'datosKardex' => $datosKardex, 
            'paginador' => $empleadosPaginados, 
            'rangoDeDias' => $rangoDeDias,
            'listaNominas' => $listaNominas, 
            'filtros' => [
                'mes' => $filtros['mes'],
                'ano' => $filtros['ano'],
                'quincena' => $filtros['quincena'],
                'perPage' => $filtros['perPage'],
                'search' => $filtros['search'] ?? '',
                'nomina' => $filtros['nomina'],
            ]
        ]);
    }
}