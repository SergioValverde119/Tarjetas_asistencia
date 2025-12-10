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
        $fechaBase = Carbon::createFromDate((int)$filtros['ano'], (int)$filtros['mes'], 1);
        $diaInicio = ((int)$filtros['quincena'] == 2) ? 16 : 1;
        $diaFin = ((int)$filtros['quincena'] == 1) ? 15 : $fechaBase->daysInMonth;
        
        $inicioPeriodo = $fechaBase->copy()->day($diaInicio)->format('Y-m-d');
        $finPeriodo = $fechaBase->copy()->day($diaFin)->format('Y-m-d');

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
            ->where('personnel_employee.enable_att', true)
            ->where(function($query) {
                $query->where('personnel_employee.is_truly_active', true)
                      ->orWhere('personnel_employee.status', 0); 
            })
            ->where(function ($query) use ($mostrarSinHorario, $inicioPeriodo, $finPeriodo) {
                if ($mostrarSinHorario) {
                    $query->whereNotExists(function ($subQuery) use ($inicioPeriodo, $finPeriodo) {
                        $subQuery->select(DB::raw(1))
                                ->from('public.att_attschedule as s')
                                ->whereColumn('s.employee_id', 'personnel_employee.id')
                                ->where('s.end_date', '>=', $inicioPeriodo)
                                ->where('s.start_date', '<=', $finPeriodo);
                    });
                } else {
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
            ->select('employee_id', 'start_time', 'end_time', 'report_symbol', 'att_leave.category_id') 
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
        // 1. Cargar el mapa (Relación ID -> Categoría)
        $mapaDeReglas = DB::table('mapeo_de_permisos')
                           ->pluck('nuestra_categoria', 'biotime_id');
        
        // 2. Obtener todas las categorías únicas que existen en la BD para inicializar contadores
        // (Esto nos prepara para el futuro: si agregas SINDICATO, aquí aparecerá)
        $categoriasPosibles = $mapaDeReglas->unique()->values()->all();
        // Aseguramos que existan las básicas aunque no estén en la BD aún
        $categoriasPosibles = array_unique(array_merge($categoriasPosibles, ['VACACION', 'OTRO']));

        $empleados = collect($empleados);

        $filasDelKardex = [];
        foreach ($empleados as $empleado) {
            
            // Inicializamos contadores fijos (los de siempre)
            $contadoresFijos = [
                'total_retardos' => 0, 
                'total_omisiones' => 0, 
                'total_faltas' => 0,
            ];

            // Inicializamos contadores dinámicos (basados en las reglas)
            $contadoresDinamicos = [];
            foreach ($categoriasPosibles as $cat) {
                $contadoresDinamicos[$cat] = 0;
            }

            $filaEmpleado = [
                'id' => $empleado->id, 
                'emp_code' => $empleado->emp_code,
                'nombre' => $empleado->first_name . ' ' . $empleado->last_name,
                'nomina' => $empleado->nomina, 
                'incidencias_diarias' => [],
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
                            // --- LÓGICA DINÁMICA ---
                            // Buscamos qué categoría es este permiso en la BD
                            $categoria = $mapaDeReglas->get($permiso->category_id, 'OTRO');
                            
                            // Incrementamos el contador de esa categoría específica
                            if (isset($contadoresDinamicos[$categoria])) {
                                $contadoresDinamicos[$categoria]++;
                            } else {
                                // Si por alguna razón la categoría no estaba inicializada
                                $contadoresDinamicos[$categoria] = 1;
                            }
                        } else {
                            // Si no encontramos regla, va a 'OTRO' o un genérico
                            $contadoresDinamicos['OTRO']++;
                        }
                    } else if ($payloadDia->absent > 0) {
                        $incidenciaDelDia = "Falto";
                        $contadoresFijos['total_faltas']++;
                    } else if ($payloadDia->clock_in == null) {
                        $incidenciaDelDia = "Sin Entrada";
                        $contadoresFijos['total_omisiones']++;
                    } else if ($payloadDia->clock_out == null) {
                        $incidenciaDelDia = "Sin Salida";
                        $contadoresFijos['total_omisiones']++;
                    } else if ($payloadDia->late > 0) {
                        $incidenciaDelDia = "R";
                        $contadoresFijos['total_retardos']++;
                    } else {
                        $incidenciaDelDia = "OK"; 
                    }
                }
                
                $filaEmpleado['incidencias_diarias'][$dia] = $incidenciaDelDia;
            }

            // --- ASIGNACIÓN FINAL (La Promesa) ---
            // Aquí traducimos lo dinámico a lo estático que espera la vista
            
            // 1. Vacaciones: Directo de su categoría
            $filaEmpleado['total_vacaciones'] = $contadoresDinamicos['VACACION'] ?? 0;
            
            // 2. Permisos: Sumamos TODO lo que no sea vacación
            // (Incapacidad, Permiso Goce, Permiso Sin Goce, etc.)
            $totalOtrosPermisos = 0;
            foreach ($contadoresDinamicos as $cat => $val) {
                if ($cat !== 'VACACION') {
                    $totalOtrosPermisos += $val;
                }
            }
            $filaEmpleado['total_permisos'] = $totalOtrosPermisos;

            // 3. Los fijos
            $filaEmpleado['total_faltas'] = $contadoresFijos['total_faltas'];
            $filaEmpleado['total_retardos'] = $contadoresFijos['total_retardos'];
            $filaEmpleado['total_omisiones'] = $contadoresFijos['total_omisiones'];
            
            // Opcional: Pasamos el desglose completo por si el frontend lo quiere usar después
            $filaEmpleado['desglose_permisos'] = $contadoresDinamicos;

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