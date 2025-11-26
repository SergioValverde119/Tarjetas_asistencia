<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class KardexRepository
{
    private $connection = 'pgsql_biotime';

    public function getNominas()
    {
        return DB::connection($this->connection)
            ->table('personnel_area')
            ->where('area_name', '!=', 'Default (Reservado)')
            ->where('area_name', '!=', 'SEDUVI') 
            ->orderBy('area_name')
            ->get(['id', 'area_name']);
    }

    public function getEmpleadosPaginados(array $filtros)
    {
        return $this->getBaseEmpleadosQuery($filtros)
            ->paginate($filtros['perPage'])
            ->withQueryString();
    }

    public function getEmpleadosTodos(array $filtros)
    {
        return $this->getBaseEmpleadosQuery($filtros)->get();
    }

    private function getBaseEmpleadosQuery(array $filtros)
    {
        return DB::connection($this->connection)
            ->table('personnel_employee')
            ->select(
                'personnel_employee.id', 
                'personnel_employee.emp_code', 
                'personnel_employee.first_name', 
                'personnel_employee.last_name', 
                'personnel_employee.hire_date',
                DB::raw("(
                    SELECT STRING_AGG(pa.area_name, ', ')
                    FROM public.personnel_employee_area pea
                    JOIN public.personnel_area pa ON pea.area_id = pa.id
                    WHERE pea.employee_id = personnel_employee.id
                    AND pa.area_name != 'SEDUVI' 
                ) as nomina")
            )
            ->where('personnel_employee.enable_att', true)
            ->where(function($query) {
                $query->where('personnel_employee.is_truly_active', true)
                      ->orWhere('personnel_employee.status', 0); 
            })
            ->when($filtros['nomina'] ?? null, function ($query, $nominaId) {
                $query->whereExists(function ($subQuery) use ($nominaId) {
                    $subQuery->select(DB::raw(1))
                             ->from('public.personnel_employee_area')
                             ->whereColumn('public.personnel_employee_area.employee_id', 'personnel_employee.id')
                             ->where('public.personnel_employee_area.area_id', $nominaId);
                });
            })
            ->when($filtros['search'], function ($query, $searchTerm) {
                $searchTerm = '%' . strtolower($searchTerm) . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where(DB::raw('LOWER(personnel_employee.first_name || \' \' || personnel_employee.last_name)'), 'LIKE', $searchTerm)
                      ->orWhere(DB::raw('LOWER(personnel_employee.last_name || \' \' || personnel_employee.first_name)'), 'LIKE', $searchTerm) 
                      ->orWhere(DB::raw('CAST(personnel_employee.emp_code AS TEXT)'), 'LIKE', $searchTerm);
                });
            })
            ->orderBy('personnel_employee.emp_code');
    }

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

    public function getPermisos(array $empleadoIDs, Carbon $fechaInicio, Carbon $fechaFin)
    {
        return DB::connection($this->connection)
            ->table('att_leave')
            ->join('att_leavecategory', 'att_leave.category_id', '=', 'att_leavecategory.id')
            ->select('employee_id', 'start_time', 'end_time', 'report_symbol')
            ->whereIn('employee_id', $empleadoIDs)
            ->where(function ($q) use ($fechaInicio, $fechaFin) {
                $q->where('start_time', '<=', $fechaFin)
                  ->where('end_time', '>=', $fechaInicio);
            })
            ->get()
            ->groupBy('employee_id');
    }
    
    public function procesarKardex($empleados, $payloadData, $permisos, $mes, $ano, $diaInicio, $diaFin)
    {
        $mapaDeReglas = DB::table('mapeo_de_permisos')
                           ->pluck('nuestra_categoria', 'biotime_report_symbol');
        
        $empleados = collect($empleados);
        $filasDelKardex = [];
        
        // --- LOG DEPURACIÓN (Limitado a 1 empleado para no saturar) ---
        $logLimit = 1;
        $logsCount = 0;

        foreach ($empleados as $empleado) {
            $filaEmpleado = [
                'id' => $empleado->id, 
                'emp_code' => $empleado->emp_code,
                'nombre' => $empleado->first_name . ' ' . $empleado->last_name,
                'nomina' => $empleado->nomina, 
                'incidencias_diarias' => [],
                'total_retardos' => 0, 'total_omisiones' => 0, 'total_faltas' => 0,
                'total_vacaciones' => 0, 'total_permisos' => 0,
            ];

            $payloadParaEmpleado = $payloadData->get($empleado->id) ?? collect();
            $permisosParaEmpleado = $permisos->get($empleado->id) ?? collect();
            
            $fechaContratacion = null;
            if ($empleado->hire_date) {
                $fechaContratacion = Carbon::parse($empleado->hire_date)->startOfDay();
            }

            if ($logsCount < $logLimit) {
                error_log(">>> [DEBUG] Procesando empleado: " . $empleado->first_name);
            }

            for ($dia = $diaInicio; $dia <= $diaFin; $dia++) {
                $incidenciaDelDia = ""; 
                
                $fechaActual = Carbon::createFromDate($ano, $mes, $dia)->startOfDay();
                $fechaString = $fechaActual->toDateString();

                if ($logsCount < $logLimit) {
                    // Imprimimos qué día es para ver si coincide con el calendario
                    $diaSemana = $fechaActual->locale('es')->dayName; // Lun, Mar...
                    error_log("    Día $dia ($fechaString - $diaSemana):");
                }

                if ($fechaActual->greaterThanOrEqualTo(Carbon::today())) {
                    $filaEmpleado['incidencias_diarias'][$dia] = null;
                    if ($logsCount < $logLimit) error_log("      -> Futuro/Hoy (NULL)");
                    continue; 
                }

                if ($fechaContratacion && $fechaActual->isBefore($fechaContratacion)) {
                    $filaEmpleado['incidencias_diarias'][$dia] = null;
                    if ($logsCount < $logLimit) error_log("      -> Pre-contrato (NULL)");
                    continue; 
                }

                $payloadDia = $payloadParaEmpleado->firstWhere('att_date', $fechaString);

                if (!$payloadDia) {
                    // Si no hay registro en BioTime, asumimos DESCANSO.
                    $incidenciaDelDia = "Descanso";
                    if ($logsCount < $logLimit) error_log("      -> Sin Payload -> DESCANSO");
                } else {
                    if ($logsCount < $logLimit) {
                        error_log("      -> Payload: absent={$payloadDia->absent}, leave={$payloadDia->leave}, clock_in={$payloadDia->clock_in}");
                    }

                    if ($payloadDia->day_off > 0) {
                        $incidenciaDelDia = "Descanso";
                    } else if ($payloadDia->leave > 0) {
                        $permiso = $this->buscarPermiso($permisosParaEmpleado, $fechaActual);
                        $incidenciaDelDia = $permiso ? $permiso->report_symbol : "Permiso";

                        if ($permiso) {
                            $categoriaLimpia = $mapaDeReglas->get($permiso->report_symbol ?? 'default', 'OTRO'); 
                            if ($categoriaLimpia === 'VACACION') {
                                $filaEmpleado['total_vacaciones']++;
                            } else {
                                $filaEmpleado['total_permisos']++; 
                            }
                        } else {
                            $filaEmpleado['total_permisos']++;
                        }
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
                    } else {
                        $incidenciaDelDia = "OK"; 
                    }
                }
                
                if ($logsCount < $logLimit) error_log("      -> Resultado: $incidenciaDelDia");
                $filaEmpleado['incidencias_diarias'][$dia] = $incidenciaDelDia;
            }
            $filasDelKardex[] = $filaEmpleado;
            $logsCount++;
        }
        return $filasDelKardex;
    }

    private function buscarPermiso($permisosEmpleado, $fechaActual) {
        foreach ($permisosEmpleado as $permiso) {
            $inicio = Carbon::parse($permiso->start_time)->startOfDay();
            $fin = Carbon::parse($permiso->end_time)->endOfDay();
            if ($fechaActual->between($inicio, $fin, true)) { return $permiso; }
        } return null;
    }
}