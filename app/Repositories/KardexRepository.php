<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class KardexRepository
{
    private $connection = 'pgsql_biotime';

    /**
     * Obtiene la lista de empleados paginada y filtrada.
     */
    public function getEmpleadosPaginados(array $filtros)
    {
        // Esta consulta base es la que vamos a reutilizar
        return $this->getBaseEmpleadosQuery($filtros)
            ->paginate($filtros['perPage'])
            ->withQueryString();
    }

    /**
     * ¡NUEVO MÉTODO!
     * Obtiene TODOS los empleados (sin paginar) para el export.
     */
    public function getEmpleadosTodos(array $filtros)
    {
        return $this->getBaseEmpleadosQuery($filtros)->get();
    }

    /**
     * ¡NUEVO MÉTODO REFACTORIZADO!
     * Lógica de consulta base para empleados.
     */
    private function getBaseEmpleadosQuery(array $filtros)
    {
        return DB::connection($this->connection)
            ->table('personnel_employee')
            ->select('id', 'emp_code', 'first_name', 'last_name', 'hire_date')
            // --- ¡LA LÍNEA MÁGICA! ---
            // Solo trae empleados que SÍ han checado en los últimos 3 meses.
            ->where('is_truly_active', true) 
            
            ->when($filtros['search'], function ($query, $searchTerm) {
                $searchTerm = '%' . strtolower($searchTerm) . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where(DB::raw('LOWER(first_name || \' \' || last_name)'), 'LIKE', $searchTerm)
                      ->orWhere(DB::raw('LOWER(last_name || \' \' || first_name)'), 'LIKE', $searchTerm) 
                      ->orWhere(DB::raw('CAST(emp_code AS TEXT)'), 'LIKE', $searchTerm);
                });
            })
            ->orderBy('emp_code');
    }

    /**
     * Obtiene los datos de payload (asistencia) para un grupo de empleados y un rango de fechas.
     */
    public function getPayloadData(array $empleadoIDs, Carbon $fechaInicio, Carbon $fechaFin)
    {
        return DB::connection($this->connection)
            ->table('att_payloadbase')
            ->select('emp_id', 'att_date', 'clock_in', 'clock_out', 'late', 'early_leave', 'absent', 'leave', 'day_off')
            ->whereIn('emp_id', $empleadoIDs)
            ->whereBetween('att_date', [$fechaInicio->toDateString(), $fechaFin->toDateString()])
            ->get()
            ->groupBy('emp_id');
    }

    /**
     * Obtiene los permisos para un grupo de empleados y un rango de fechas.
     */
    public function getPermisos(array $empleadoIDs, Carbon $fechaInicio, Carbon $fechaFin)
    {
        return DB::connection($this->connection)
            ->table('att_leave')
            ->join('att_leavecategory', 'att_leave.category_id', '=', 'att_leavecategory.id')
            // --- ¡CAMBIO! Traemos el report_symbol (la clave) ---
            ->select('employee_id', 'start_time', 'end_time', 'report_symbol') 
            ->whereIn('employee_id', $empleadoIDs)
            ->where(function ($q) use ($fechaInicio, $fechaFin) {
                $q->where('start_time', '<=', $fechaFin)
                  ->where('end_time', '>=', $fechaInicio);
            })
            ->get()
            ->groupBy('employee_id');
    }
    
    /**
     * Procesa los datos crudos para armar el Kárdex.
     */
    public function procesarKardex($empleados, $payloadData, $permisos, $mes, $ano, $diaInicio, $diaFin)
    {
        // --- ¡NUEVA LÓGICA! ---
        // 1. Cargar nuestro "diccionario" de reglas en memoria UNA SOLA VEZ.
        //    (Lee de la BD default 'bd_tarjetas', donde creamos la tabla)
        $mapaDeReglas = DB::table('mapeo_de_permisos')
                           ->pluck('nuestra_categoria', 'biotime_report_symbol');
        
        $filasDelKardex = [];
        foreach ($empleados as $empleado) {
            $filaEmpleado = [
                'emp_code' => $empleado->emp_code,
                'nombre' => $empleado->first_name . ' ' . $empleado->last_name,
                'incidencias_diarias' => [],
                'total_retardos' => 0, 'total_omisiones' => 0, 'total_faltas' => 0,
                'total_vacaciones' => 0, 'total_permisos' => 0,
            ];

            $payloadParaEmpleado = $payloadData->get($empleado->id) ?? collect();
            $permisosParaEmpleado = $permisos->get($empleado->id) ?? collect();
            
            // Aseguramos que la fecha de contratación se compare como fecha (sin hora)
            $fechaContratacion = Carbon::parse($empleado->hire_date)->startOfDay();

            for ($dia = $diaInicio; $dia <= $diaFin; $dia++) {
                $incidenciaDelDia = "";
                $fechaActual = Carbon::createFromDate($ano, $mes, $dia)->startOfDay();
                $fechaString = $fechaActual->toDateString();

                // Lógica de Días Previos a la Contratación
                if ($fechaActual->isBefore($fechaContratacion)) {
                    $incidenciaDelDia = ""; 
                    $filaEmpleado['incidencias_diarias'][$dia] = $incidenciaDelDia;
                    continue; 
                }

                $payloadDia = $payloadParaEmpleado->firstWhere('att_date', $fechaString);

                // Lógica de Incidencias
                if ($fechaActual->dayOfWeek == 0 || $fechaActual->dayOfWeek == 6) {
                    $incidenciaDelDia = "Descanso";
                } else if (!$payloadDia) {
                    // Si no hay payload, PERO el empleado está activo (is_truly_active=true)
                    // y no es fin de semana, ES UNA FALTA.
                    $incidenciaDelDia = "Falto";
                    $filaEmpleado['total_faltas']++;
                } else {
                    if ($payloadDia->day_off > 0) {
                        $incidenciaDelDia = "Descanso";
                    
                    // --- ¡TODA ESTA LÓGICA ES NUEVA! ---
                    } else if ($payloadDia->leave > 0) {
                        $permiso = $this->buscarPermiso($permisosParaEmpleado, $fechaActual);
                        // Mostramos el símbolo original (ej. '102PV')
                        $incidenciaDelDia = $permiso ? $permiso->report_symbol : "Permiso";

                        if ($permiso) {
                            // 2. Buscamos la traducción en nuestro "diccionario" (el mapa de memoria)
                            //    Si el símbolo es nulo o vacío, usamos 'default'
                            $categoriaLimpia = $mapaDeReglas->get($permiso->report_symbol ?? 'default', 'OTRO'); // 'OTRO' es el default

                            // 3. Contamos usando la traducción
                            if ($categoriaLimpia === 'VACACION') {
                                $filaEmpleado['total_vacaciones']++;
                            } else {
                                $filaEmpleado['total_permisos']++; // Todo lo demás
                            }
                        } else {
                            // Si por alguna razón no se encontró (raro), lo mandamos a permisos
                            $filaEmpleado['total_permisos']++;
                        }
                    // --- FIN DE LA NUEVA LÓGICA ---

                    } else if ($payloadDia->absent > 0) {
                        $incidenciaDelDia = "Falto";
                        $filaEmpleado['total_faltas']++;
                    } else if ($payloadDia->clock_in == null) {
                        $incidenciaDelDia = "Sin Entrada";
                        $filaEmpleado['total_omisiones']++;
                    } else if ($payloadDia->clock_out == null) {
                        $incidenciaDelDia = "Sin Salida";
                        $filaEmpleado['total_omisiones']++;
                    } else if ($payloadDia->late > 0) {
                        $incidenciaDelDia = "R";
                        $filaEmpleado['total_retardos']++;
                    }
                }
                $filaEmpleado['incidencias_diarias'][$dia] = $incidenciaDelDia;
            }
            $filasDelKardex[] = $filaEmpleado;
        }
        return $filasDelKardex;
    }

    /**
     * Busca un permiso para un empleado en una fecha específica.
     */
    private function buscarPermiso($permisosEmpleado, $fechaActual) {
        foreach ($permisosEmpleado as $permiso) {
            $inicio = Carbon::parse($permiso->start_time)->startOfDay();
            $fin = Carbon::parse($permiso->end_time)->endOfDay();
            if ($fechaActual->between($inicio, $fin, true)) { return $permiso; }
        } return null;
    }
}