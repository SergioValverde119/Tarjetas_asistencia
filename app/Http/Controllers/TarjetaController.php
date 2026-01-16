<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\TarjetaService; 
use App\Models\HistorialDescarga;
use Inertia\Inertia;
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
        $pkBiotime = $user->biotime_id;
        $year = 2025;

        $empleadoData = $this->tarjetaService->buscarEmpleadoPorBiotimeId($pkBiotime);
        $resumenFaltas = [];

        if ($empleadoData) {
            $resumenFaltas = $this->tarjetaService->calcularResumenFaltasAnual($empleadoData->id, $year);
        } else {
            $empleadoData = [
                'id' => $pkBiotime ?? 'N/A', 'emp_code' => 'N/A',
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
     * --- VISTA DE DISPONIBILIDAD ---
     */
    public function indexUsers(Request $request)
    {
        // 1. Obtenemos TODOS los empleados
        $todosEmpleados = $this->tarjetaService->obtenerTodosLosEmpleados();
        
        // 2. Filtro búsqueda
        if ($request->has('search') && $request->search != '') {
            $search = strtolower($request->search);
            $todosEmpleados = array_filter($todosEmpleados, function($emp) use ($search) {
                return str_contains(strtolower($emp->first_name), $search) || 
                       str_contains(strtolower($emp->last_name), $search) ||
                       str_contains($emp->emp_code, $search);
            });
        }

        // 3. Paginación manual
        $perPage = 10;
        $page = $request->input('page', 1);
        $offset = ($page - 1) * $perPage;
        
        $empleadosPaginados = array_slice($todosEmpleados, $offset, $perPage);
        $total = count($todosEmpleados);

        // 4. Calculamos estatus
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

        // CAMBIO: Apuntamos al nuevo nombre de archivo
        return Inertia::render('DisponibilidadTarjetas', [
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
        $request->validate(['month' => 'required', 'year' => 'required']);
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
        try {
            $users = $this->tarjetaService->obtenerTodosLosEmpleados();
            return response()->json(['users' => $users]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
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