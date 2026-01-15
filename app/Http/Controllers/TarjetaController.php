<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\TarjetaService; // Importamos tu nuevo Servicio
use App\Models\HistorialDescarga;
use Inertia\Inertia;
use Exception;

class TarjetaController extends Controller
{
    protected $tarjetaService;

    // Inyección de Dependencias: Laravel te da el servicio automáticamente
    public function __construct(TarjetaService $tarjetaService)
    {
        $this->tarjetaService = $tarjetaService;
    }

    /**
     * VISTA PRINCIPAL: "Mi Tarjeta"
     */
    public function indexIndividual()
    {
        $user = Auth::user();
        $pkBiotime = $user->biotime_id;
        $year = 2025;

        // 1. Delegamos al Servicio la búsqueda del empleado
        $empleadoData = $this->tarjetaService->buscarEmpleadoPorBiotimeId($pkBiotime);
        
        $resumenFaltas = [];

        // 2. Si existe, delegamos el cálculo pesado de faltas al Servicio
        if ($empleadoData) {
            $resumenFaltas = $this->tarjetaService->calcularResumenFaltasAnual($empleadoData->id, $year);
        } else {
            // Fallback visual si no está vinculado
            $empleadoData = [
                'id' => $pkBiotime ?? 'N/A', 'emp_code' => 'N/A',
                'first_name' => $user->name, 'last_name' => '',
                'department_name' => 'No vinculado', 'job_title' => ''
            ];
        }

        // 3. Historial de descargas (Esto es local de Laravel, se queda aquí)
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
     * API: OBTENER DETALLE MES (Para el PDF)
     */
    public function getSchedule(Request $request)
    {error_log($request);
        try {
            $request->validate([
                'emp_id' => 'required',
                'month' => 'required',
                'year' => 'required'
            ]);

            // ¡Magia! Una sola línea hace todo el trabajo sucio gracias al Servicio
            $datos = $this->tarjetaService->obtenerDatosPorMes(
                $request->emp_id, 
                $request->month, 
                $request->year
            );

            return response()->json($datos);
            error_log($datos);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * REGISTRAR DESCARGA
     */
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

    // --- MÉTODOS ADMINISTRATIVOS ---

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