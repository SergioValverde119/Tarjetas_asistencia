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
use Carbon\Carbon;

class TarjetaController extends Controller
{
    protected $tarjetaService;

    public function __construct(TarjetaService $tarjetaService)
    {
        $this->tarjetaService = $tarjetaService;
    }

    private function getRollingMonths()
    {
        $months = [];
        // Queremos los últimos 12 meses terminando en el mes que acaba de concluir
        for ($i = 12; $i >= 1; $i--) {
            $date = Carbon::now()->subMonths($i);
            $months[] = [
                'id'    => $date->month,
                'year'  => $date->year,
                'name'  => $date->translatedFormat('F'), // Nombre completo (Enero, Febrero...)
                'label' => $date->translatedFormat('M Y')  // Etiqueta corta (Ene 2026)
            ];
        }
        return $months;
    }

    public function indexIndividual()
    {
        $user = Auth::user();
        // Buscamos los datos reales del empleado en BioTime
        $empleadoData = $this->tarjetaService->buscarEmpleadoPorBiotimeId($user->biotime_id);

        if ($empleadoData) {
            error_log("DEBUG - indexIndividual - Depto: " . ($empleadoData->department_name ?? 'NULL'));
        }

        $resumenFaltas = [];
        $rollingMonths = $this->getRollingMonths();

        if ($empleadoData) {
            // Recorremos los 12 meses dinámicos para armar el mapa de faltas
            foreach ($rollingMonths as $m) {
                // Consultamos las faltas específicas de ese mes y ESE año
                $faltas = $this->tarjetaService->obtenerFaltasEspecificas($empleadoData->id, $m['id'], $m['year']);
                
                if (!empty($faltas)) {
                    // Estructura: resumenFaltas[2026][1] = [5, 12, 15]
                    $resumenFaltas[$m['year']][$m['id']] = $faltas;
                }
            }
        } else {
            // Datos de respaldo si el usuario no está vinculado a BioTime
            $empleadoData = [
                'id' => $user->biotime_id ?? 'N/A', 
                'emp_code' => 'N/A',
                'first_name' => $user->name, 
                'last_name' => '',
                'department_name' => 'No vinculado', 
                'job_title' => ''
            ];
        }

        // Buscamos el historial de descargas del usuario para estos 12 meses específicos
        // Para que la vista lo reconozca fácil, mandamos una lista de "mes-año"
        $descargasPrevias = HistorialDescarga::where('user_id', $user->id)
            ->where(function($query) use ($rollingMonths) {
                foreach ($rollingMonths as $m) {
                    $query->orWhere(function($q) use ($m) {
                        $q->where('month', $m['id'])->where('year', $m['year']);
                    });
                }
            })
            ->get(['month', 'year'])
            ->map(fn($d) => "{$d->month}-{$d->year}")
            ->toArray();

        return Inertia::render('MiTarjeta', [
            'empleado' => $empleadoData,
            'descargasPrevias' => $descargasPrevias, // Ejemplo: ["12-2025", "1-2026"]
            'resumenFaltas' => $resumenFaltas,       // Ejemplo: { 2026: { 1: [5, 8] } }
            'rollingMonths' => $rollingMonths       // Lista para el v-for de la tabla
        ]);
    }

    /**
     * REPORTE DE DISPONIBILIDAD (Backend Filtering)
     */
    public function indexDisponibilidad(Request $request)
{
    set_time_limit(0); 
    ini_set('memory_limit', '512M');

    $rollingMonths = $this->getRollingMonths();
    $allEmployees = $this->tarjetaService->obtenerTodosLosEmpleados();

    // Filtro usuarios locales
    $idsConCuenta = User::whereNotNull('biotime_id')->pluck('biotime_id')->map(fn($id) => (string)$id)->toArray();
    $allEmployees = array_filter($allEmployees, fn($emp) => in_array((string)$emp->id, $idsConCuenta));

    // 1. Filtro de Búsqueda (Nómina/Nombre)
    if ($request->search) {
        $search = strtolower($request->search);
        $allEmployees = array_filter($allEmployees, fn($emp) => 
            str_contains(strtolower($emp->first_name ?? ''), $search) ||
            str_contains(strtolower($emp->last_name ?? ''), $search) ||
            str_contains($emp->emp_code ?? '', $search)
        );
    }

    // --- 2. LÓGICA DE FILTRADO POR ESTATUS (FIX) ---
    $month = $request->month;
    $status = $request->status;

    if ($month && $status) {
        // Buscamos el año que corresponde a ese mes en nuestra ventana deslizante
        $targetMonth = collect($rollingMonths)->firstWhere('id', (int)$month);
        $year = $targetMonth ? $targetMonth['year'] : Carbon::now()->year;

        $filtered = [];
        foreach ($allEmployees as $emp) {
            $tieneFaltas = $this->tarjetaService->tieneFaltasEnMes($emp->id, $month, $year);
            
            if ($status === 'blocked' && $tieneFaltas) {
                $filtered[] = $emp;
            } elseif ($status === 'ok' && !$tieneFaltas) {
                $filtered[] = $emp;
            }
        }
        $allEmployees = $filtered;
    }

    // 3. Paginación
    $currentPage = LengthAwarePaginator::resolveCurrentPage();
    $perPage = 10;
    $currentItems = array_slice($allEmployees, ($currentPage - 1) * $perPage, $perPage);
    
    foreach ($currentItems as &$emp) {
        $semaforo = [];
        foreach ($rollingMonths as $m) {
            $tieneFaltas = $this->tarjetaService->tieneFaltasEnMes($emp->id, $m['id'], $m['year']);
            $semaforo[] = $tieneFaltas ? 'blocked' : 'ok';
        }
        $emp->semaforo = $semaforo;
    }

    return Inertia::render('DisponibilidadTarjetas', [
        'empleados' => new LengthAwarePaginator($currentItems, count($allEmployees), $perPage, $currentPage, ['path' => $request->url()]),
        // IMPORTANTE: Devolver todos los filtros para que la vista no se limpie
        'filters' => $request->only(['search', 'month', 'status']), 
        'rollingMonths' => $rollingMonths,
        'year' => Carbon::now()->year
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
            $estatusMeses = []; 
            $listaFinal[] = [
                'id' => $emp->id,
                'emp_code' => $emp->emp_code,
                'nombre' => $emp->first_name . ' ' . $emp->last_name,
                'department_name' => $emp->department_name,
                
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