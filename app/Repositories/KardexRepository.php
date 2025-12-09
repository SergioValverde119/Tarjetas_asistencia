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

    public function getCatalogoPermisos()
    {
        return DB::connection($this->connection)
            ->table('att_leavecategory')
            ->pluck('category_name', 'report_symbol'); 
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
        // Calculamos las fechas del periodo seleccionado para filtrar los horarios
        $fechaBase = Carbon::createFromDate((int)$filtros['ano'], (int)$filtros['mes'], 1);
        $diaInicio = ((int)$filtros['quincena'] == 2) ? 16 : 1;
        $diaFin = ((int)$filtros['quincena'] == 1) ? 15 : $fechaBase->daysInMonth;
        
        $inicioPeriodo = $fechaBase->copy()->day($diaInicio)->format('Y-m-d');
        $finPeriodo = $fechaBase->copy()->day($diaFin)->format('Y-m-d');

        // Verificamos si el filtro "sin_horario" está activo
        $mostrarSinHorario = isset($filtros['sin_horario']) && filter_var($filtros['sin_horario'], FILTER_VALIDATE_BOOLEAN);

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
            
            // --- CORRECCIÓN: FILTRO ESTRICTO ---
            // 1. Debe estar habilitado en BioTime (quita bajas oficiales)
            ->where('personnel_employee.enable_att', true)
            
            // 2. Debe tener actividad reciente (quita fantasmas)
            // Quitamos el "OR status=0" para que sea estricto con el robot.
            ->where('personnel_employee.is_truly_active', true)
            // -----------------------------------

            ->where(function ($query) use ($mostrarSinHorario, $inicioPeriodo, $finPeriodo) {
                if ($mostrarSinHorario) {
                    // Si el botón está activo: Buscamos los que NO tienen horario
                    $query->whereNotExists(function ($subQuery) use ($inicioPeriodo, $finPeriodo) {
                        $subQuery->select(DB::raw(1))
                                ->from('public.att_attschedule as s')
                                ->whereColumn('s.employee_id', 'personnel_employee.id')
                                ->where('s.end_date', '>=', $inicioPeriodo)
                                ->where('s.start_date', '<=', $finPeriodo);
                    });
                } else {
                    // Si el botón está apagado (Default): Buscamos los que SÍ tienen horario
                    $query->whereExists(function ($subQuery) use ($inicioPeriodo, $finPeriodo) {
                        $subQuery->select(DB::raw(1))
                                ->from('public.att_attschedule as s')
                                ->whereColumn('s.employee_id', 'personnel_employee.id')
                                ->where('s.end_date', '>=', $inicioPeriodo)
                                ->where('s.start_date', '<=', $finPeriodo);
                    });
                }
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
            ->select('employee_id', 'start_time', 'end_time', 'report_symbol', 'att_leave.category_id') // <-- Incluimos category_id
            ->whereIn('employee_id', $empleadoIDs)
            ->where(function ($q) use ($fechaInicio, $fechaFin) {
                $q->where('start_time', '<=', $fechaFin)
                  ->where('end_time', '>=', $fechaInicio);
            })
            ->get()
            ->groupBy('employee_id');
    }

    public function getHorarioActual($empleadoId)
    {
        $turnoAsignado = DB::connection($this->connection)
            ->table('att_attschedule as s')
            ->join('att_attshift as sh', 's.shift_id', '=', 'sh.id')
            ->where('s.employee_id', $empleadoId)
            ->where('s.start_date', '<=', now()->toDateString())
            ->where('s.end_date', '>=', now()->toDateString())
            ->select('sh.id', 'sh.alias as nombre_turno')
            ->first();

        if (!$turnoAsignado) return null;

        $detalles = DB::connection($this->connection)
            ->table('att_shiftdetail as sd')
            ->join('att_timeinterval as ti', 'sd.time_interval_id', '=', 'ti.id')
            ->where('sd.shift_id', $turnoAsignado->id)
            ->select('sd.day_index', 'sd.in_time', 'sd.out_time', 'ti.duration')
            ->orderBy('sd.day_index')
            ->get();

        return [
            'nombre' => $turnoAsignado->nombre_turno,
            'dias' => $detalles 
        ];
    }
    
    public function procesarKardex($empleados, $payloadData, $permisos, $mes, $ano, $diaInicio, $diaFin)
    {
        // Cargamos las reglas usando el ID como clave
        $mapaDeReglas = DB::table('mapeo_de_permisos')
                           ->pluck('nuestra_categoria', 'biotime_id');
        
        $empleados = collect($empleados);

        $filasDelKardex = [];
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

            for ($dia = $diaInicio; $dia <= $diaFin; $dia++) {
                $incidenciaDelDia = ""; 
                
                $fechaActual = Carbon::createFromDate($ano, $mes, $dia)->startOfDay();
                $fechaString = $fechaActual->toDateString();

                if ($fechaActual->greaterThanOrEqualTo(Carbon::today())) {
                    $filaEmpleado['incidencias_diarias'][$dia] = null;
                    continue; 
                }

                if ($fechaContratacion && $fechaActual->isBefore($fechaContratacion)) {
                    $filaEmpleado['incidencias_diarias'][$dia] = null;
                    continue; 
                }

                $payloadDia = $payloadParaEmpleado->firstWhere('att_date', $fechaString);

                if (!$payloadDia) {
                    $incidenciaDelDia = "Descanso";
                } else {
                    if ($payloadDia->day_off > 0) {
                        $incidenciaDelDia = "Descanso";
                    } else if ($payloadDia->leave > 0) {
                        $permiso = $this->buscarPermiso($permisosParaEmpleado, $fechaActual);
                        $incidenciaDelDia = $permiso ? $permiso->report_symbol : "Permiso";

                        if ($permiso) {
                            // --- BUSCAMOS POR ID ---
                            $categoriaLimpia = $mapaDeReglas->get($permiso->category_id, 'OTRO'); 
                            
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
            if ($fechaActual->between($inicio, $fin, true)) { return $permiso; }
        } return null;
    }
}