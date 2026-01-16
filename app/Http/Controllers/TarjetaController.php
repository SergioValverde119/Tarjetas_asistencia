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
     * REPORTE DE DISPONIBILIDAD (Semáforo con Filtro Unido)
     */
    public function indexDisponibilidad(Request $request)
    {
        // --- CORRECCIÓN: Aumentamos límites para evitar timeout ---
        set_time_limit(300); // 5 minutos de espera máxima
        ini_set('memory_limit', '512M'); // Aumentamos memoria para procesar listas grandes

        error_log("--- [FILTRO] Procesando Disponibilidad ---");
        $year = 2025;
        
        // 1. Obtener empleados
        $allEmployees = $this->tarjetaService->obtenerTodosLosEmpleados();

        // 2. Filtrar solo usuarios con cuenta
        $idsConCuenta = User::whereNotNull('biotime_id')
            ->pluck('biotime_id')
            ->map(fn($id) => (string)$id)
            ->toArray();

        $allEmployees = array_filter($allEmployees, function($emp) use ($idsConCuenta) {
            return in_array((string)$emp->id, $idsConCuenta);
        });

        // 3. Filtro de Texto
        if ($request->has('search') && $request->search) {
            $search = strtolower($request->search);
            $allEmployees = array_filter($allEmployees, function($emp) use ($search) {
                return str_contains(strtolower($emp->first_name ?? ''), $search) ||
                       str_contains(strtolower($emp->last_name ?? ''), $search) ||
                       str_contains($emp->emp_code ?? '', $search);
            });
        }

        // 4. FILTRO DE ESTADO Y MES (Lógica Unida)
        $filterMonth = $request->input('month');   // Ej: "1" (Enero)
        $filterStatus = $request->input('status'); // Ej: "blocked" o "ok"

        if ($filterMonth || $filterStatus) {
            $filteredList = [];
            
            foreach ($allEmployees as $emp) {
                // Calculamos el resumen para evaluar al empleado
                // (Esta operación es pesada, por eso aumentamos el set_time_limit arriba)
                $resumen = $this->tarjetaService->calcularResumenFaltasAnual($emp->id, $year);
                $cumpleCondicion = true;

                // CASO A: Filtro específico por MES (Prioridad)
                if ($filterMonth) {
                    $m = (int)$filterMonth;
                    // ¿Tiene faltas en ESTE mes?
                    $tieneFaltasEnMes = isset($resumen[$m]) && count($resumen[$m]) > 0;

                    if ($filterStatus === 'blocked') {
                        // Quiero BLOQUEADOS en Enero -> Si NO tiene faltas, lo descarto.
                        if (!$tieneFaltasEnMes) $cumpleCondicion = false;
                    } elseif ($filterStatus === 'ok') {
                        // Quiero LIMPIOS en Enero -> Si TIENE faltas, lo descarto.
                        if ($tieneFaltasEnMes) $cumpleCondicion = false;
                    }
                } 
                // CASO B: Filtro Global (Todo el año)
                else {
                    $tieneAlgunaFalta = count($resumen) > 0;
                    
                    if ($filterStatus === 'blocked') {
                        if (!$tieneAlgunaFalta) $cumpleCondicion = false;
                    } elseif ($filterStatus === 'ok') {
                        if ($tieneAlgunaFalta) $cumpleCondicion = false;
                    }
                }

                if ($cumpleCondicion) {
                    $emp->cached_resumen = $resumen; // Guardamos para no recalcular
                    $filteredList[] = $emp;
                }
            }
            $allEmployees = $filteredList;
        }

        // 5. Paginación
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 10;
        $currentItems = array_slice($allEmployees, ($currentPage - 1) * $perPage, $perPage);
        
        // 6. Armado final visual
        foreach ($currentItems as &$emp) {
            // Usamos caché si existe, si no calculamos
            $resumen = $emp->cached_resumen ?? $this->tarjetaService->calcularResumenFaltasAnual($emp->id, $year);
            
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
            unset($emp->cached_resumen);
        }

        $paginatedItems = new LengthAwarePaginator($currentItems, count($allEmployees), $perPage);
        $paginatedItems->setPath($request->url());

        return Inertia::render('DisponibilidadTarjetas', [
            'empleados' => $paginatedItems,
            'filters' => $request->only(['search', 'month', 'status']),
            'year' => $year
        ]);
    }

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
        $year = 2025;
        $listaFinal = [];
        foreach ($empleadosPaginados as $emp) {
            $faltas = $this->tarjetaService->calcularResumenFaltasAnual($emp->id, $year);
            $estatusMeses = [];
            for ($m = 1; $m <= 12; $m++) {
                if ($year == now()->year && $m > now()->month) {
                    $estatusMeses[$m] = 'future';
                } elseif (isset($faltas[$m]) && count($faltas[$m]) > 0) {
                    $estatusMeses[$m] = 'blocked';
                } else {
                    $estatusMeses[$m] = 'ok';
                }
            }
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