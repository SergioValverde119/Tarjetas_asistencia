<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Repositories\KardexRepository; // <-- 1. Importa el Repositorio

class KardexController extends Controller
{
    // 2. Guarda una instancia del repositorio
    protected $kardexRepo;

    // 3. Pídele a Laravel que te lo "inyecte" en el constructor
    public function __construct(KardexRepository $kardexRepo)
    {
        $this->kardexRepo = $kardexRepo;
    }

    /**
     * Muestra la página de visualización web del Kardex.
     */
    public function index(Request $request)
    {
        // El controlador ya no sabe de 'DB' ni de 'att_payloadbase'.
        // ¡Solo coordina!
        return $this->mostrarVista($request);
    }
    
    /**
     * Busca y filtra los datos del Kardex.
     */
    public function buscar(Request $request)
    {
        // Esta función se queda igual, es pura lógica de HTTP
        $validatedData = $request->validate([
            'mes' => 'required|integer|min:1|max:12',
            'ano' => 'required|integer|min:2020|max:2030',
            'quincena' => 'required|integer|min:0|max:2',
            'perPage' => 'required|integer|in:10,20,50,200',
            'search' => 'nullable|string|max:50',
            // --- NUEVA LÍNEA ---
            'ocultar_inactivos' => 'nullable|boolean',
        ]);
        
        return redirect()->route('kardex.index', $validatedData);
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
            // --- NUEVA LÍNEA ---
            'ocultar_inactivos' => (bool)$request->input('ocultar_inactivos', false),
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
        // ¡Tu controlador ahora es súper limpio!
        
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
                // --- NUEVA LÍNEA ---
                'ocultar_inactivos' => $filtros['ocultar_inactivos'],
            ]
        ]);
    }
    
    // ¡La función privada 'procesarKardex' y 'buscarPermiso' desaparecen de aquí!
    // Ahora viven en el Repositorio.
}