<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class KardexRepository
{
    // Define la conexión para no repetirla
    private $connection = 'pgsql_biotime';

    /**
     * Obtiene la lista de empleados paginada y filtrada.
     */
    public function getEmpleadosPaginados(array $filtros)
    {
        return DB::connection($this->connection)
            ->table('personnel_employee')
            ->select('id', 'emp_code', 'first_name', 'last_name', 'hire_date') // <-- Pedimos hire_date
            
            // --- AÑADIMOS LOS FILTROS DE ESTATUS ---
            ->where('is_active', true) // Sigue siendo buena idea filtrar por los obvios
            ->where('deleted', false)

            // --- LÓGICA DEL NUEVO FILTRO INTELIGENTE ---
            ->when($filtros['ocultar_inactivos'], function ($query) {
                // Si el checkbox está MARCADO, solo mostramos empleados
                // QUE SÍ TENGAN al menos una checada en los últimos 90 días.
                // Usamos WHERE EXISTS porque es mucho más rápido que un JOIN.
                $fechaLimite = now()->subDays(90)->toDateString();
                
                $query->whereExists(function ($subQuery) use ($fechaLimite) {
                    $subQuery->select(DB::raw(1))
                             ->from('public.iclock_transaction AS t')
                             // Une el empleado con sus transacciones
                             ->whereColumn('t.emp_id', 'public.personnel_employee.id')
                             // Busca al menos una checada reciente
                             ->where('t.punch_time', '>=', $fechaLimite);
                             // Opcional: también puedes incluir checadas manuales si cuentan
                             // ->orWhereExists(...) de att_manuallog
                });
            })

            ->when($filtros['search'], function ($query, $searchTerm) {
                $searchTerm = '%' . strtolower($searchTerm) . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where(DB::raw('LOWER(first_name || \' \' || last_name)'), 'LIKE', $searchTerm)
                      ->orWhere(DB::raw('CAST(emp_code AS TEXT)'), 'LIKE', $searchTerm);
                });
            })
            ->orderBy('emp_code')
            ->paginate($filtros['perPage'])
            ->withQueryString();
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

            // === ¡NUEVA LÍNEA! ===
            // Convertimos la fecha de contratación a un objeto Carbon para compararla
            $fechaContratacion = Carbon::parse($empleado->hire_date);

            for ($dia = $diaInicio; $dia <= $diaFin; $dia++) {
                $incidenciaDelDia = "";
                $fechaActual = Carbon::createFromDate($ano, $mes, $dia);
                $fechaString = $fechaActual->toDateString();

                // === ¡NUEVA REGLA DE NEGOCIO! ===
                // Si el día que estamos revisando es ANTERIOR a la fecha de contratación...
                if ($fechaActual->isBefore($fechaContratacion)) {
                    $incidenciaDelDia = ""; // No es una falta, simplemente no trabajaba aquí.
                    $filaEmpleado['incidencias_diarias'][$dia] = $incidenciaDelDia;
                    continue; // ... saltamos al siguiente día.
                }
                // ===================================
                
                $payloadDia = $payloadParaEmpleado->firstWhere('att_date', $fechaString);

                if ($fechaActual->dayOfWeek == 0 || $fechaActual->dayOfWeek == 6) {
                    $incidenciaDelDia = "Descanso";
                } else if (!$payloadDia) {
                    $incidenciaDelDia = "Falto";
                    $filaEmpleado['total_faltas']++;
                } else {
                    if ($payloadDia->day_off > 0) {
                        $incidenciaDelDia = "Descanso";
                    } else if ($payloadDia->leave > 0) {
                        $permiso = $this->buscarPermiso($permisosParaEmpleado, $fechaActual);
                        $incidenciaDelDia = $permiso ? $permiso->report_symbol : "Permiso";
                        if ($permiso && str_starts_with($permiso->report_symbol, 'V')) {
                            $filaEmpleado['total_vacaciones']++;
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