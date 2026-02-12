<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class KardexRepository
{
    private $connection = 'pgsql_biotime';

    // =============================================================================================
    // 1. MÉTODOS DE SOPORTE (Catálogos y Horarios)
    // =============================================================================================

    public function getNominas()
    {
        return DB::connection($this->connection)
            ->table('personnel_area')
            ->where('area_name', '!=', 'Default (Reservado)')
            ->orderBy('area_name')
            ->get(['id', 'area_name']);
    }

    public function getCatalogoPermisos()
    {
        return DB::connection($this->connection)
            ->table('att_leavecategory')
            ->pluck('category_name', 'report_symbol'); 
    }

    public function getDiasFestivos(Carbon $fechaInicio, Carbon $fechaFin)
    {
        return DB::connection($this->connection)
            ->table('att_holiday')
            ->whereBetween('start_date', [$fechaInicio->toDateString(), $fechaFin->toDateString()])
            ->get();
    }

    /**
     * Obtiene el horario actual asignado (para mostrar en encabezados o detalles)
     */
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

    // =============================================================================================
    // 2. CONSULTAS DE EMPLEADOS (Buscador del Kárdex)
    // =============================================================================================

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
        // ... (Configuración de fechas igual que antes) ...
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
            ->where('personnel_employee.status', 0) // Solo activos
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

    // =============================================================================================
    // 3. EXTRACCIÓN DE DATOS MASIVOS (Aquí está la clave de la unificación)
    // =============================================================================================

    public function getPayloadData(array $empleadoIDs, Carbon $fechaInicio, Carbon $fechaFin)
    {
        // Hacemos una consulta masiva usando WhereIn para optimizar el Kárdex,
        // pero trayendo LAS MISMAS COLUMNAS que usa el Servicio de Tarjetas (all_punches, duration, etc.)
        return DB::connection($this->connection)
            ->table('att_payloadbase as apb')
            ->leftJoin('att_timeinterval as ti', 'apb.timetable_id', '=', 'ti.id')
            ->select(
                'apb.emp_id', 
                'apb.att_date', 
                'apb.clock_in', 
                'apb.clock_out', 
                'apb.late', 
                'apb.early_leave', 
                'apb.absent', 
                'apb.leave', 
                'apb.day_off',
                
                // GENERAMOS 'all_punches' AL VUELO: Esto arregla el error de "columna no existe".
                // Buscamos en iclock_transaction todas las checadas de ese día y las juntamos.
                DB::raw("(
                    SELECT STRING_AGG(TO_CHAR(punch_time, 'YYYY-MM-DD HH24:MI:SS'), ',' ORDER BY punch_time ASC)
                    FROM public.iclock_transaction 
                    WHERE emp_id = apb.emp_id AND punch_time::date = apb.att_date
                ) as all_punches"),
                
                'ti.in_time',      
                'ti.work_time_duration as duration' 
            )
            ->whereIn('apb.emp_id', $empleadoIDs)
            ->whereBetween('apb.att_date', [$fechaInicio->toDateString(), $fechaFin->toDateString()])
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

    // =============================================================================================
    // 4. LÓGICA DE NEGOCIO (EL CEREBRO COMPARTIDO)
    // =============================================================================================

    public function procesarKardex($empleados, $payloadData, $permisos, $mes, $ano, $diaInicio, $diaFin)
    {
        try {
            $mapaDeReglas = DB::connection('pgsql')->table('leave_mappings')->pluck('leave_policy_id', 'external_leave_id');
        } catch (Exception $e) { $mapaDeReglas = collect(); }
        
        $fechaInicioMes = Carbon::createFromDate($ano, $mes, 1)->startOfDay();
        $fechaFinMes = Carbon::createFromDate($ano, $mes, 1)->endOfMonth()->endOfDay();
        $festivos = $this->getDiasFestivos($fechaInicioMes, $fechaFinMes);

        $empleados = collect($empleados);
        $filasDelKardex = [];

        foreach ($empleados as $empleado) {
            
            $contadores = ['retardos' => 0, 'omisiones' => 0, 'faltas' => 0];
            
            $filaEmpleado = [
                'id' => $empleado->id, 
                'emp_code' => $empleado->emp_code,
                'nombre' => $empleado->first_name . ' ' . $empleado->last_name,
                'nomina' => $empleado->nomina, 
                'incidencias_diarias' => [],
            ];

            $payloadParaEmpleado = $payloadData->get($empleado->id) ?? collect();
            $permisosParaEmpleado = $permisos->get($empleado->id) ?? collect();
            $fechaContratacion = $empleado->hire_date ? Carbon::parse($empleado->hire_date)->startOfDay() : null;

            for ($dia = $diaInicio; $dia <= $diaFin; $dia++) {
                $fechaActual = Carbon::createFromDate($ano, $mes, $dia)->startOfDay();
                $fechaString = $fechaActual->toDateString();

                // Filtros de fecha
                if ($fechaActual->greaterThanOrEqualTo(Carbon::today()) || ($fechaContratacion && $fechaActual->isBefore($fechaContratacion))) {
                    $filaEmpleado['incidencias_diarias'][$dia] = null;
                    continue; 
                }

                // 1. Buscar Datos
                $esFestivo = $festivos->contains(fn($h) => str_starts_with($h->start_date, $fechaString));
                $permiso = $this->buscarPermiso($permisosParaEmpleado, $fechaActual);
                $payloadDia = $payloadParaEmpleado->firstWhere('att_date', $fechaString);

                $incidencia = "";

                // --- ÁRBOL DE DECISIÓN UNIFICADO ---

                // A. Fin de Semana
                if ($fechaActual->isWeekend()) {
                    $incidencia = "DESC";
                }
                // B. Festivo (Prioridad sobre faltas)
                else if ($esFestivo && (!$payloadDia || (!$payloadDia->clock_in && !$payloadDia->clock_out))) {
                    $incidencia = "J"; 
                }
                // C. Permiso (Prioridad sobre faltas)
                else if ($permiso) {
                    $incidencia = $permiso->report_symbol; 
                }
                // D. Sin registro en absoluto
                else if (!$payloadDia) {
                    $incidencia = "Falto";
                    $contadores['faltas']++;
                }
                else {
                    // E. Análisis de Asistencia (Aquí aplicamos la magia de la Tarjeta)
                    
                    // 1. Ejecutamos la limpieza de huellas (Salida anticipada, etc.)
                    $this->procesarHuellas($payloadDia);

                    // 2. Evaluamos el resultado limpio
                    
                    // Si no tiene horario asignado, es Falta (regla de tu servicio)
                    // a menos que tenga checadas, en cuyo caso es asistencia fuera de horario
                    if (!$payloadDia->clock_in && !$payloadDia->clock_out && empty($payloadDia->in_time)) {
                        $incidencia = "Falto"; // O "S/H"
                        $contadores['faltas']++;
                    }
                    else if ($payloadDia->clock_in && $payloadDia->clock_out) {
                        // Tiene ambas, evaluamos retardo con tolerancia
                        $eval = $this->evaluarRetardo($payloadDia);
                        if ($eval === 'OK') {
                            $incidencia = "OK";
                        } else {
                            $incidencia = "R";
                            $contadores['retardos']++;
                        }
                    } else {
                        // Le falta una checada
                        if (!$payloadDia->clock_in && !$payloadDia->clock_out) {
                            $incidencia = "Falto";
                            $contadores['faltas']++;
                        } else {
                            // Aquí caen las "Salidas Anticipadas" que borró procesarHuellas
                            $incidencia = !$payloadDia->clock_in ? "S/E" : "S/S";
                            $contadores['omisiones']++;
                        }
                    }
                }
                
                $filaEmpleado['incidencias_diarias'][$dia] = $incidencia;
            }

            // Asignar totales
            $filaEmpleado['total_faltas'] = $contadores['faltas'];
            $filaEmpleado['total_retardos'] = $contadores['retardos'];
            $filaEmpleado['total_omisiones'] = $contadores['omisiones'];

            $filasDelKardex[] = $filaEmpleado;
        }
        return $filasDelKardex;
    }

    // =============================================================================================
    // 5. FUNCIONES PRIVADAS (Clonadas de TarjetaService)
    // =============================================================================================

    private function procesarHuellas($reg)
    {
        // Si no tiene horario, no tocamos nada
        if (empty($reg->in_time)) return;

        // Reset para recalcular limpio
        $reg->clock_in = null;
        $reg->clock_out = null;

        $fechaStr = Carbon::parse($reg->att_date)->format('Y-m-d');
        
        // DST
        $date = Carbon::parse($reg->att_date);
        $inicioPrimavera = Carbon::parse("first sunday of april $date->year");
        $finOtono = Carbon::parse("last sunday of october $date->year");
        $esVerano = $date->greaterThanOrEqualTo($inicioPrimavera) && $date->lessThan($finOtono);
        $horasAjuste = $esVerano ? 1 : 0;

        $duracionMinutos = $reg->duration ?? 480;
        $targetIn = Carbon::parse($fechaStr . ' ' . $reg->in_time);
        $targetOut = (clone $targetIn)->addMinutes($duracionMinutos);

        $punchesRaw = array_filter(explode(',', $reg->all_punches ?? ''), fn($p) => !empty(trim($p)));
        $punchesAjustados = [];
        $lastAdded = null;

        foreach ($punchesRaw as $pStr) {
            try {
                $p = Carbon::parse(trim($pStr));
                if ($horasAjuste != 0) $p->addHours($horasAjuste);

                if (!$lastAdded || abs($p->diffInSeconds($lastAdded)) > 30) {
                    $punchesAjustados[] = $p;
                    $lastAdded = $p;
                }
            } catch (Exception $e) {}
        }

        $bestIn = null; $bestOut = null;
        $minDistIn = 999999; $minDistOut = 999999;

        foreach ($punchesAjustados as $punch) {
            $distIn = abs($targetIn->diffInMinutes($punch, false));
            $distOut = abs($targetOut->diffInMinutes($punch, false));

            if ($distIn < $distOut) {
                if ($distIn < $minDistIn) { $minDistIn = $distIn; $bestIn = $punch; }
            } else {
                if ($distOut < $minDistOut) { $minDistOut = $distOut; $bestOut = $punch; }
            }
        }

        $umbral = 30; // Tolerancia de búsqueda de huella

        if ($bestIn && $minDistIn <= $umbral) $reg->clock_in = $bestIn->format('Y-m-d H:i:s');
        
        if ($bestOut && $minDistOut <= $umbral) {
            // REGLA: Si salió antes de la hora objetivo, se borra la salida
            if ($bestOut->lessThan($targetOut)) {
                $reg->clock_out = null; 
            } else {
                $reg->clock_out = $bestOut->format('Y-m-d H:i:s');
            }
        }
    }

    private function evaluarRetardo($registro)
    {
        if (!$registro->clock_in || !$registro->in_time) return 'OK';

        $fechaCheckIn = Carbon::parse($registro->att_date)->format('Y-m-d');
        $horaEntradaEstandar = Carbon::parse($fechaCheckIn . ' ' . $registro->in_time);
        $horaRealEntrada = Carbon::parse($registro->clock_in);
        
        $diferenciaMinutos = $horaEntradaEstandar->diffInMinutes($horaRealEntrada, false);
        
        // Tolerancia de 11 minutos
        return ($diferenciaMinutos <= 11) ? 'OK' : 'R';
    }

    private function buscarPermiso($permisosEmpleado, $fechaActual) {
        foreach ($permisosEmpleado as $permiso) {
            $inicio = Carbon::parse($permiso->start_time)->startOfDay();
            $fin = Carbon::parse($permiso->end_time)->endOfDay();
            if ($fechaActual->between($inicio, $fin, true)) { return $permiso; }
        } return null;
    }
}