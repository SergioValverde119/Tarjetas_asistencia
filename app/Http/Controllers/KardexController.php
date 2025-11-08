<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class KardexController extends Controller
{
    /**
     * Muestra la página de visualización web del Kardex.
     * Esta función maneja la CARGA INICIAL (GET).
     */
    public function index(Request $request)
    {
        return $this->mostrarVista($request);
    }
    
    /**
     * Busca y filtra los datos del Kardex.
     * Esta función maneja el FORMULARIO (POST).
     */
    public function buscar(Request $request)
    {
        // Validar los datos que vienen del formulario
        $request->validate([
            'mes' => 'required|integer|min:1|max:12',
            'ano' => 'required|integer|min:2020|max:2030',
            'quincena' => 'required|integer|min:0|max:2',
            'perPage' => 'required|integer|in:10,20,50,200',
            'search' => 'nullable|string|max:50', // <-- ¡Validación correcta!
        ]);
        
        // Redirige de vuelta a la página 'index' con los filtros como parámetros GET.
        return redirect()->route('kardex.index', $request->all()); // <-- ¡Esto está perfecto!
    }

    /**
     * Lógica principal (separada) para mostrar la vista.
     */
    private function mostrarVista(Request $request)
    {  
        // 1. OBTENER FILTROS (ahora desde la URL)
        $mes = $request->input('mes', date('m'));
        $ano = $request->input('ano', date('Y'));
        $quincena = $request->input('quincena', 0);
        $perPage = $request->input('perPage', 10);
        $search = $request->input('search'); // <-- ¡Tu filtro de búsqueda!
        
        $fechaBase = Carbon::createFromDate($ano, $mes, 1);
        $diasTotalesDelMes = $fechaBase->daysInMonth;

        $diaInicio = ($quincena == 2) ? 16 : 1;
        $diaFin = ($quincena == 1) ? 15 : $diasTotalesDelMes;
        $rangoDeDias = range($diaInicio, $diaFin);

        // --- 2. LEER DATOS DE LA "BASE GEMELA" (¡CON EL FILTRO DE BÚSQUEDA AHORA SÍ!) ---
        
        $empleadosPaginados = DB::connection('pgsql_biotime')
            ->table('personnel_employee')
            ->select('id', 'emp_code', 'first_name', 'last_name')
            
            
            // --- ¡¡ESTE ES EL BLOQUE QUE FALTABA!! ---
            // Solo se aplica SI $search NO está vacío
            ->when($search, function ($query, $searchTerm) {
                // 1. Prepara el término de búsqueda
                //    (ej. "reyes" -> "%reyes%")
                //    (usamos strtolower para que sea case-insensitive)
                $searchTerm = '%' . strtolower($searchTerm) . '%';
                
                $query->where(function ($q) use ($searchTerm) {
                    // 2. Busca en el Nombre Completo (ignora mayús/minús)
                    $q->where(DB::raw('LOWER(CONCAT(first_name, \' \', last_name))'), 'LIKE', $searchTerm)
                      // 3. O busca en el ID de empleado (convertido a texto)
                      ->orWhere(DB::raw('CAST(emp_code AS TEXT)'), 'LIKE', $searchTerm);
                });
            })

            
            // --- FIN DEL BLOQUE NUEVO ---
            
            ->orderBy('emp_code')
            ->paginate($perPage)
            ->withQueryString(); // <-- Esto hace que la paginación recuerde el filtro

        $empleadoIDsEnPagina = $empleadosPaginados->pluck('id');
        error_log("Filtro 'search' recibido: " . $empleadosPaginados);

        // (El resto de las consultas están bien, ya que dependen de $empleadoIDsEnPagina)
        $payloadData = DB::connection('pgsql_biotime')
            ->table('att_payloadbase')
            ->select('emp_id', 'att_date', 'clock_in', 'clock_out', 'late', 'early_leave', 'absent', 'leave', 'day_off')
            ->whereIn('emp_id', $empleadoIDsEnPagina)
            ->whereMonth('att_date', $mes)
            ->whereYear('att_date', $ano)
            ->get()
            ->groupBy('emp_id');

        $permisos = DB::connection('pgsql_biotime')
            ->table('att_leave')
            ->join('att_leavecategory', 'att_leave.category_id', '=', 'att_leavecategory.id')
            ->select('employee_id', 'start_time', 'end_time', 'report_symbol')
            ->whereIn('employee_id', $empleadoIDsEnPagina)
            ->whereYear('start_time', $ano)
            ->whereMonth('start_time', $mes)
            ->get()
            ->groupBy('employee_id');

        // --- 3. PROCESAR DATOS (ARMAR LA TABLA DEL KARDEX) ---
        $datosKardex = $this->procesarKardex(
            $empleadosPaginados->items(), 
            $payloadData,
            $permisos, 
            $mes, $ano, $diaInicio, $diaFin
        );
        
        // --- 4. RENDERIZAR LA PÁGINA VUE (SIN LAYOUT) ---
        return Inertia::render('Kardex/Index', [
            'datosKardex' => $datosKardex, 
            'paginador' => $empleadosPaginados, 
            'rangoDeDias' => $rangoDeDias,
            'filtros' => [
                'mes' => (int)$mes,
                'ano' => (int)$ano,
                'quincena' => (int)$quincena,
                'perPage' => (int)$perPage,
                'search' => $search ?? '' // <-- ¡Le devolvemos el filtro a Vue!
            ]
        ]);
    }

    // (El resto del archivo: procesarKardex, buscarPermiso, etc. se queda 100% igual)
    // ...
    private function procesarKardex($empleados, $payloadData, $permisos, $mes, $ano, $diaInicio, $diaFin)
    {
        $filasDelKardex = [];

        foreach ($empleados as $empleado) {
            
            $filaEmpleado = [
                'emp_code' => $empleado->emp_code,
                'nombre' => $empleado->first_name . ' ' . $empleado->last_name,
                'incidencias_diarias' => [],
                'total_retardos' => 0,
                'total_omisiones' => 0,
                'total_faltas' => 0,
                'total_vacaciones' => 0,
                'total_permisos' => 0,
            ];

            $payloadParaEmpleado = $payloadData->get($empleado->id) ?? collect();
            $permisosParaEmpleado = $permisos->get($empleado->id) ?? collect();

            for ($dia = $diaInicio; $dia <= $diaFin; $dia++) {
                
                $incidenciaDelDia = ""; // <-- Vacío = Asistencia Normal (VERDE)
                $fechaActual = Carbon::createFromDate($ano, $mes, $dia);
                $fechaString = $fechaActual->toDateString(); 

                $payloadDia = $payloadParaEmpleado->firstWhere('att_date', $fechaString);

                if ($fechaActual->dayOfWeek == 0 || $fechaActual->dayOfWeek == 6) {
                    $incidenciaDelDia = "Descanso"; // (GRIS)
                
                } else if (!$payloadDia) {
                    $incidenciaDelDia = "Falto"; // (ROJO)
                    $filaEmpleado['total_faltas']++;

                } else {
                    
                    if ($payloadDia->day_off > 0) {
                        $incidenciaDelDia = "Descanso"; // (GRIS)

                    } else if ($payloadDia->leave > 0) {
                        $permiso = $this->buscarPermiso($permisosParaEmpleado, $fechaActual);
                        $incidenciaDelDia = $permiso ? $permiso->report_symbol : "Permiso"; // (AZUL)
                        
                        if ($permiso && str_starts_with($permiso->report_symbol, 'V')) {
                            $filaEmpleado['total_vacaciones']++;
                        } else {
                            $filaEmpleado['total_permisos']++;
                        }

                    } else if ($payloadDia->absent > 0) {
                        $incidenciaDelDia = "Falto"; // (ROJO)
                        $filaEmpleado['total_faltas']++;

                    } else if ($payloadDia->clock_in == null) {
                        $incidenciaDelDia = "Sin Entrada"; // (AMARILLO)
                        $filaEmpleado['total_omisiones']++;
                    
                    } else if ($payloadDia->clock_out == null) {
                        $incidenciaDelDia = "Sin Salida"; // (AMARILLO)
                        $filaEmpleado['total_omisiones']++;
                    
                    } else if ($payloadDia->late > 0) {
                        $incidenciaDelDia = "R"; // "R" de Retardo (AZUL)
                        $filaEmpleado['total_retardos']++;
                    
                    } else {
                        $incidenciaDelDia = ""; // (VERDE)
                    }
                }
                $filaEmpleado['incidencias_diarias'][$dia] = $incidenciaDelDia;
            }
            $filasDelKardex[] = $filaEmpleado;
        }
        return $filasDelKardex;
    }

    private function buscarPermiso($permisosEmpleado, $fechaActual) {
        foreach ($permisosEmpleado as $permiso) {
            $inicio = Carbon::parse($permiso->start_time)->startOfDay();
            $fin = Carbon::parse($permiso->end_time)->endOfDay();
            if ($fechaActual->between($inicio, $fin, true)) {
                return $permiso;
            }
        }
        return null;
    }
}