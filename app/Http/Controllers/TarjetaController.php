<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\TarjetaService; 
use App\Models\HistorialDescarga;
use App\Models\User; 
use Inertia\Inertia;
use Illuminate\Pagination\LengthAwarePaginator;
use Exception;

class TarjetaController extends Controller
{
    protected $tarjetaService;

    public function __construct(TarjetaService $tarjetaService)
    {
        $this->tarjetaService = $tarjetaService;
    }

    // --- MÓDULO: MI TARJETA ---
    public function indexIndividual()
    {
        $user = Auth::user();
        $empleadoData = $this->tarjetaService->buscarEmpleadoPorBiotimeId($user->biotime_id);
        $resumenFaltas = [];
        $year = 2025;

        if ($empleadoData) {
            $resumenFaltas = $this->tarjetaService->calcularResumenFaltasAnual($empleadoData->id, $year);
        } else {
            $empleadoData = [
                'id' => $user->biotime_id ?? 'N/A', 'emp_code' => 'N/A',
                'first_name' => $user->name, 'last_name' => '',
                'department_name' => 'No vinculado', 'job_title' => ''
            ];
        }

        $descargasPrevias = HistorialDescarga::where('user_id', $user->id)
            ->where('year', $year)
            ->pluck('month')
            ->toArray();

        return Inertia::render('MiTarjeta', [
            'empleado' => $empleadoData,
            'descargasPrevias' => $descargasPrevias,
            'resumenFaltas' => $resumenFaltas
        ]);
    }

    /**
     * REPORTE DE DISPONIBILIDAD (Versión Rápida)
     * Estrategia: Paginar primero, calcular después.
     * Los filtros de Estatus/Mes se manejarán visualmente en el Frontend.
     */
    public function indexDisponibilidad(Request $request)
    {
        $year = 2025;
        
        // 1. Obtener empleados (Consulta ligera)
        $allEmployees = $this->tarjetaService->obtenerTodosLosEmpleados();

        // 2. Filtro Local: Solo usuarios con cuenta
        $idsConCuenta = User::whereNotNull('biotime_id')
            ->pluck('biotime_id')
            ->map(fn($id) => (string)$id)
            ->toArray();

        $allEmployees = array_filter($allEmployees, function($emp) use ($idsConCuenta) {
            return in_array((string)$emp->id, $idsConCuenta);
        });

        // 3. Filtro de Texto (Buscador)
        if ($request->has('search') && $request->search) {
            $search = strtolower($request->search);
            $allEmployees = array_filter($allEmployees, function($emp) use ($search) {
                return str_contains(strtolower($emp->first_name ?? ''), $search) ||
                       str_contains(strtolower($emp->last_name ?? ''), $search) ||
                       str_contains($emp->emp_code ?? '', $search);
            });
        }

        // 4. Paginación INMEDIATA (Esto garantiza la velocidad)
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 100;
        $currentItems = array_slice($allEmployees, ($currentPage - 1) * $perPage, $perPage);
        
        // 5. Cálculo SOLO para los 10 visibles
        foreach ($currentItems as &$emp) {
            $resumen = $this->tarjetaService->calcularResumenFaltasAnual($emp->id, $year);
            
            $semaforo = [];
            for ($m = 1; $m <= 12; $m++) {
                if ($year == now()->year && $m > now()->month) {
                    $semaforo[] = 'future';
                } elseif (isset($resumen[$m]) && count($resumen[$m]) > 0) {
                    $semaforo[] = 'blocked';
                } else {
                    $semaforo[] = 'ok';
                }
            }
            $emp->semaforo = $semaforo;
        }

        $paginatedItems = new LengthAwarePaginator($currentItems, count($allEmployees), $perPage);
        $paginatedItems->setPath($request->url());

        return Inertia::render('DisponibilidadTarjetas', [
            'empleados' => $paginatedItems,
            'filters' => $request->only(['search', 'month', 'status']), // Pasamos filtros para que el Front sepa qué hacer
            'year' => $year
        ]);
    }

    // --- MÓDULO: TARJETAS GENERALES ---
    public function indexUsers(Request $request)
    {
        $todosEmpleados = $this->tarjetaService->obtenerTodosLosEmpleados();
        
        if ($request->has('search') && $request->search != '') {
            $search = strtolower($request->search);
            $todosEmpleados = array_filter($todosEmpleados, function($emp) use ($search) {
                return str_contains(strtolower($emp->first_name ?? ''), $search) || 
                       str_contains(strtolower($emp->last_name ?? ''), $search) ||
                       str_contains($emp->emp_code ?? '', $search);
            });
        }

        $perPage = 10;
        $page = $request->input('page', 1);
        $offset = ($page - 1) * $perPage;
        
        $empleadosPaginados = array_slice($todosEmpleados, $offset, $perPage);
        $total = count($todosEmpleados);

        // Para este módulo simple, quizás no necesitas calcular el semáforo completo si es lento
        // pero lo dejo igual para mantener consistencia si lo usabas.
        $year = 2025;
        $listaFinal = [];

        foreach ($empleadosPaginados as $emp) {
            // Si esto es lento aquí, podrías quitarlo, ya que este módulo es "Buscar y Descargar"
            $estatusMeses = []; 
            // ... (Lógica simplificada si prefieres velocidad extrema aquí) ...
            
            $listaFinal[] = [
                'id' => $emp->id,
                'emp_code' => $emp->emp_code,
                'nombre' => $emp->first_name . ' ' . $emp->last_name,
                'depto' => $emp->department_name,
                'estatus_anual' => $estatusMeses 
            ];
        }

        return Inertia::render('BuscarTarjetas', [
            'empleados' => [
                'data' => $listaFinal,
                'current_page' => (int)$page,
                'last_page' => ceil($total / $perPage),
                'total' => $total
            ],
            'filters' => $request->only(['search'])
        ]);
    }

    // --- APIs ---
    public function getSchedule(Request $request)
    {
        try {
            $datos = $this->tarjetaService->obtenerDatosPorMes(
                $request->emp_id, $request->month, $request->year
            );
            return response()->json($datos);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function downloadPdf(Request $request)
    {
        try {
            HistorialDescarga::updateOrInsert(
                ['user_id' => Auth::id(), 'month' => $request->month, 'year' => $request->year],
                ['downloaded_at' => now(), 'ip_address' => $request->ip()]
            );
            return response()->json(['status' => 'success']);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getUsers(Request $request) {
        $users = $this->tarjetaService->obtenerTodosLosEmpleados();
        return response()->json(['users' => $users]);
    }

    public function indexLogs(Request $request) {
        $query = \App\Models\HistorialDescarga::with('user')->orderBy('downloaded_at', 'desc');
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('biotime_id', 'like', "%{$search}%");
            });
        }
        return Inertia::render('LogsDescargas', [
            'logs' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['search'])
        ]);
    }
}