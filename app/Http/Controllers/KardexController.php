<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Repositories\KardexRepository;
use App\Exports\KardexExport; // <-- ¡NUEVA IMPORTACIÓN!
use Maatwebsite\Excel\Facades\Excel; // <-- ¡NUEVA IMPORTACIÓN!

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
     * Busca y filtra los datos del Kardex.
     */
    public function buscar(Request $request)
    {
        // Esta validación ahora es solo para el POST
        $validatedData = $request->validate([
            'mes' => 'required|integer|min:1|max:12',
            'ano' => 'required|integer|min:2020|max:2030',
            'quincena' => 'required|integer|min:0|max:2',
            'perPage' => 'required|integer|in:10,20,50,200',
            'search' => 'nullable|string|max:50',
            // 'ocultar_inactivos' ya no se necesita
        ]);
        
        return redirect()->route('kardex.index', $validatedData);
    }

    /**
     * ¡NUEVO MÉTODO!
     * Maneja la solicitud de exportación a Excel.
     */
    public function exportar(Request $request)
    {
        // Validamos los filtros que llegan por GET
        $filtros = $request->validate([
            'mes' => 'required|integer|min:1|max:12',
            'ano' => 'required|integer|min:2020|max:2030',
            'quincena' => 'required|integer|min:0|max:2',
            'perPage' => 'nullable|integer',
            'search' => 'nullable|string|max:50',
            // 'ocultar_inactivos' ya no se necesita
        ]);
        
        // Generar nombre de archivo dinámico
        $mesNombre = Carbon::create()->month($filtros['mes'])->monthName;
        $fileName = sprintf('Kardex_%s_%s_Q%s.xlsx', $filtros['ano'], $mesNombre, $filtros['quincena']);

        // Pasamos los filtros Y el repositorio que ya tiene el controlador
        return Excel::download(new KardexExport($filtros, $this->kardexRepo), $fileName);
    }

    /**
     * Lógica principal (separada) para mostrar la vista.
     */
    private function mostrarVista(Request $request)
    {
        // 1. OBTENER FILTROS
        $filtros = [
            'mes' => (int)$request->input('mes', date('m')),
            'ano' => (int)$request->input('ano', date('Y')),
            'quincena' => (int)$request->input('quincena', 0),
            'perPage' => (int)$request->input('perPage', 10),
            'search' => $request->input('search'),
            // 'ocultar_inactivos' ya no se necesita
        ];
        
        // 2. DEFINIR FECHAS
        $fechaBase = Carbon::createFromDate($filtros['ano'], $filtros['mes'], 1);
        $diasTotalesDelMes = $fechaBase->daysInMonth;
        $diaInicio = ($filtros['quincena'] == 2) ? 16 : 1;
        $diaFin = ($filtros['quincena'] == 1) ? 15 : $diasTotalesDelMes;
        $rangoDeDias = range($diaInicio, $diaFin);

        $fechaInicioMes = Carbon::createFromDate($filtros['ano'], $filtros['mes'], 1)->startOfDay();
        $fechaFinMes = $fechaBase->copy()->endOfMonth()->endOfDay();
        
        // --- 3. PEDIR DATOS AL REPOSITORIO ---
        $empleadosPaginados = $this->kardexRepo->getEmpleadosPaginados($filtros);
        $empleadoIDsEnPagina = $empleadosPaginados->pluck('id')->toArray();

        $payloadData = $this->kardexRepo->getPayloadData($empleadoIDsEnPagina, $fechaInicioMes, $fechaFinMes);
        $permisos = $this->kardexRepo->getPermisos($empleadoIDsEnPagina, $fechaInicioMes, $fechaFinMes);

        // --- 4. PROCESAR DATOS (TAMBIÉN EN EL REPO) ---
        $datosKardex = $this->kardexRepo->procesarKardex(
            $empleadosPaginados->items(), 
            $payloadData,
            $permisos, 
            $filtros['mes'], $filtros['ano'], $diaInicio, $diaFin
        );
        
        // --- 5. RENDERIZAR LA PÁGINA VUE ---
        return Inertia::render('Kardex/Index', [
            'datosKardex' => $datosKardex, 
            'paginador' => $empleadosPaginados, 
            'rangoDeDias' => $rangoDeDias,
            'filtros' => [
                'mes' => $filtros['mes'],
                'ano' => $filtros['ano'],
                'quincena' => $filtros['quincena'],
                'perPage' => $filtros['perPage'],
                'search' => $filtros['search'] ?? '',
                // 'ocultar_inactivos' ya no se necesita
            ]
        ]);
    }
}