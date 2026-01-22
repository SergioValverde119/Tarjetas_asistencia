<?php

namespace App\Services;

use App\Repositories\TarjetaRepository;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class TarjetaService
{
    /**
     * @var TarjetaRepository
     */
    protected $repository;

    public function __construct(TarjetaRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Obtener todos los empleados.
     */
    public function obtenerTodosLosEmpleados()
    {
        try {
            return $this->repository->getAllEmployees();
        } catch (Exception $e) {
            Log::error("Error en TarjetaService@obtenerTodosLosEmpleados: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca los datos de un empleado en BioTime.
     */
    public function buscarEmpleadoPorBiotimeId($biotimeId)
    {
        if (!$biotimeId) return null;

        try {
            $allEmployees = $this->repository->getAllEmployees();
            foreach ($allEmployees as $emp) {
                if (strval($emp->id) === strval($biotimeId)) {
                    return $emp;
                }
            }
        } catch (Exception $e) {
            Log::error("Error en TarjetaService@buscarEmpleado: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Calcula el resumen de faltas Y RETARDOS GRAVES anual.
     * Esto alimenta los semáforos rojos/verdes.
     */
    public function calcularResumenFaltasAnual($empleadoId, $year)
    {
        $resumenFaltas = [];
        
        try {
            // --- AGREGADO: NUEVA LÓGICA DE UNIFICACIÓN ---
            // Para evitar que el semáforo salga verde cuando hay RG, ahora consultamos
            // mes por mes usando la misma lógica del detalle. Esto garantiza consistencia.
            for ($m = 1; $m <= 12; $m++) {
                if ($year == now()->year && $m > now()->month) break;

                // Llamamos a obtenerDatosPorMes para usar exactamente la misma vara de medir
                $datosMes = $this->obtenerDatosPorMes($empleadoId, $m, $year);
                
                $diasMalos = [];
                foreach ($datosMes['registros'] as $dia) {
                    if ($dia['calificacion'] === 'F' || $dia['calificacion'] === 'RG') {
                        $diasMalos[] = Carbon::parse($dia['dia'])->day;
                    }
                }

                if (!empty($diasMalos)) {
                    $resumenFaltas[$m] = $diasMalos;
                }
            }

            /* COMENTADO: Lógica antigua que fallaba al procesar el año completo por pérdida de contexto de turnos
            $startOfYear = Carbon::createFromDate($year, 1, 1)->startOfYear()->format('Y-m-d H:i:s');
            $endOfYear = Carbon::createFromDate($year, 12, 31)->endOfYear()->format('Y-m-d H:i:s');

            $registrosYear = $this->repository->getAttendanceRecords($empleadoId, $startOfYear, $endOfYear);
            $holidaysYear = $this->repository->getHolidays($startOfYear, $endOfYear);
            
            $permisosYear = [];
            if (method_exists($this->repository, 'getPermissions')) {
                $permisosYear = $this->repository->getPermissions($empleadoId, $startOfYear, $endOfYear);
            }

            for ($m = 1; $m <= 12; $m++) {
                if ($year == now()->year && $m > now()->month) break;

                $inicioMes = Carbon::createFromDate($year, $m, 1)->startOfMonth()->format('Y-m-d');
                $finMes = Carbon::createFromDate($year, $m, 1)->endOfMonth()->format('Y-m-d');

                $registrosMes = array_filter($registrosYear, fn($r) => Carbon::parse($r->att_date)->month == $m);

                $procesado = $this->transformarRegistros($registrosMes, $holidaysYear, $permisosYear, $inicioMes, $finMes);

                $diasMalos = [];
                foreach ($procesado as $dia) {
                    if ($dia['calificacion'] === 'F' || $dia['calificacion'] === 'RG') {
                        $diasMalos[] = Carbon::parse($dia['dia'])->day;
                    }
                }

                if (!empty($diasMalos)) {
                    $resumenFaltas[$m] = $diasMalos;
                }
            }
            */
        } catch (Exception $e) {
            Log::error("Error calculando resumen anual: " . $e->getMessage());
        }

        return $resumenFaltas;
    }

    /**
     * Obtiene el detalle completo mensual.
     */
    public function obtenerDatosPorMes($empleadoId, $month, $year)
    {
        try {
            $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth()->format('Y-m-d H:i:s');
            $endOfMonth = Carbon::createFromDate($year, $month, 1)->endOfMonth()->format('Y-m-d H:i:s');

            $registrosRaw = $this->repository->getAttendanceRecords($empleadoId, $startOfMonth, $endOfMonth);
            $holidaysRaw = $this->repository->getHolidays($startOfMonth, $endOfMonth);
            
            // RESTAURADO: Búsqueda de permisos según original
            $permisosRaw = method_exists($this->repository, 'getPermissions') 
                ? $this->repository->getPermissions($empleadoId, $startOfMonth, $endOfMonth) 
                : [];

            if (empty($registrosRaw) && empty($permisosRaw)) {
                return [
                    'horario' => null, 
                    'registros' => [],
                    'department_name'=> 'Sin área' // AGREGADO: Corrección de tilde
                    ];
            }

            // RESTAURADO: Llamada con parámetros originales
            $registrosProcesados = $this->transformarRegistros($registrosRaw, $holidaysRaw, $permisosRaw, $startOfMonth, $endOfMonth);

            $departmentName = $registrosRaw[0]->department_name ?? 'Sin departamento';

            $horarioTexto = 'Sin horario';
            
            // --- AGREGADO: RESTAURADA LÓGICA DE HORARIO MEDIANTE OFF_TIME (SÍ FUNCIONABA) ---
            if (isset($registrosRaw[0]) && $registrosRaw[0]->in_time && $registrosRaw[0]->off_time) {
                $horarioTexto = $registrosRaw[0]->in_time . ' A ' . $registrosRaw[0]->off_time;
            }
            
            /* COMENTADO: Lógica antigua basada en duración manual en PHP
            if (isset($registrosRaw[0]) && $registrosRaw[0]->in_time && $registrosRaw[0]->duration) {
                try {
                    $startTime = Carbon::createFromFormat('H:i:s', $registrosRaw[0]->in_time);
                    $endTime = (clone $startTime)->addMinutes($registrosRaw[0]->duration);
                    $horarioTexto = $startTime->format('H:i:s') . ' A ' . $endTime->format('H:i:s');
                } catch (Exception $e) {
                    $horarioTexto = $registrosRaw[0]->in_time;
                }
            }
            */

            return [
                'horario' => $horarioTexto,
                'department_name' => $departmentName,
                'registros' => $registrosProcesados
            ];

        } catch (Exception $e) {
            Log::error("Error en TarjetaService@obtenerDatosPorMes: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * LÓGICA PRIVADA PARA TRANSFORMAR REGISTROS
     * RESTAURADO: Parámetros originales ($registros, $holidays, $permisos, $startDate, $endDate)
     */
    private function transformarRegistros($registros, $holidays, $permisos, $startDate, $endDate)
    {
        $retardosLevesPrevios = 0;
        $resultados = [];

        // AGREGADO: Mantengo el foreach para respetar la lógica de apply_reason que viene del SQL
        // pero integrado con la estructura original.
        foreach ($registros as $reg) {
            $fechaActualStr = Carbon::parse($reg->att_date)->format('Y-m-d');
            $date = Carbon::parse($fechaActualStr);
            
            // AGREGADO: PRIORIDAD 1: Esto resuelve el problema de Sergio (ID 14154). 
            // Si el SQL encontró incidencia, ganará sobre el bucle manual.
            // AGREGADO: trim() para evitar que espacios en blanco se tomen como justificación válida.
            $motivoSQL = !empty(trim($reg->apply_reason ?? '')) ? $reg->apply_reason : null;

            // RESTAURADO: Bucle manual de permisos del archivo original
            $permisoDia = null;
            foreach ($permisos as $p) {
                $pStart = Carbon::parse($p->start_date);
                $pEnd = Carbon::parse($p->end_date);
                if ($date->between($pStart, $pEnd)) {
                    $permisoDia = $p;
                    break;
                }
            }

            $holidayDia = null;
            foreach ($holidays as $hol) {
                if (str_starts_with($hol->start_date, $fechaActualStr)) {
                    $holidayDia = $hol;
                    break;
                }
            }

            // --- REGLAS ---

            // AGREGADO: Priorizamos el motivo del SQL si existe
            if ($motivoSQL) {
                $resultados[] = $this->crearFila($fechaActualStr, $reg, 'J', $motivoSQL);
                continue;
            }

            if ($date->isWeekend()) {
                $resultados[] = $this->crearFila($fechaActualStr, null, 'DESC', '');
                continue;
            }

            // RESTAURADO: Evaluación permisoDia manual del original
            if ($permisoDia) {
                $resultados[] = $this->crearFila($fechaActualStr, $reg, 'J', $permisoDia->reason ?? 'Permiso');
                continue;
            }

            // RESTAURADO: Lógica de descanso original
            if (!$reg->clock_in && !$reg->clock_out && (!$reg->timetable_name || ($holidayDia && isset($reg->enable_holiday) && $reg->enable_holiday === true))) {
                $resultados[] = $this->crearFila($fechaActualStr, null, 'DESC', $holidayDia ? $holidayDia->alias : '');
                continue;
            }

            // RESTAURADO: Lógica de falta original
            if (!$reg->clock_in) {
                $resultados[] = $this->crearFila($fechaActualStr, null, 'F', '');
                continue;
            }

            // --- AGREGADO: NUEVA LÓGICA DE CLASIFICACIÓN SIN ACUMULACIÓN ---
            $calificacion = $this->evaluarRetardo($reg, $retardosLevesPrevios);
            
            /* COMENTADO: Se quita la regla de acumulación de 4 retardos leves = 1 grave
            if ($calificacion === 'RL') $retardosLevesPrevios++;
            if ($retardosLevesPrevios >= 4) {
                $calificacion = 'RG';
                $retardosLevesPrevios = 0;
            }
            */
            
            // Si es F o RG pero tiene justificación en el registro, pasa a 'J'
            // AGREGADO: trim() para mayor seguridad en la validación
            if (($calificacion === 'F' || $calificacion === 'RG') && !empty(trim($reg->apply_reason ?? ''))) {
                $calificacion = 'J';
            }

            $resultados[] = $this->crearFila($fechaActualStr, $reg, $calificacion, $reg->apply_reason ?? '');
        }

        return $resultados;
    }

    private function crearFila($fecha, $registro, $calificacion, $obs) {
        return [
            'dia' => $fecha,
            'checkin' => ($registro && $registro->clock_in) ? Carbon::parse($registro->clock_in)->format('H:i:s') : '',
            'checkout' => ($registro && $registro->clock_out) ? Carbon::parse($registro->clock_out)->format('H:i:s') : '',
            'calificacion' => $calificacion,
            'observaciones' => $obs
        ];
    }

    private function evaluarRetardo($registro, $retardosLevesPrevios)
    {
        if (empty($registro->clock_in)) return 'F';
        
        // RESTAURADO: Lógica basada en check_in como el original
        $fechaCheckIn = Carbon::parse($registro->check_in)->format('Y-m-d');
        $horaEntradaEstandar = Carbon::parse($fechaCheckIn . ' ' . $registro->in_time);
        $horaRealEntrada = Carbon::parse($registro->clock_in);
        
        $diferenciaMinutos = $horaEntradaEstandar->diffInMinutes($horaRealEntrada, false);
        
        $tolerance = $registro->allow_late - 1;

        if ($diferenciaMinutos <= $tolerance) return 'OK';

        // --- AGREGADO: CLASIFICACIÓN DIRECTA SIN LÍMITE DE FALTA POR TIEMPO ---
        
        // 1. Si está dentro del rango de retardo leve (hasta 21 minutos) siempre es RL
        if ($diferenciaMinutos > $tolerance && $diferenciaMinutos <= 21) {
            /* COMENTADO: Se quita la verificación de acumulados
            return ($retardosLevesPrevios >= 4) ? 'RG' : 'RL';
            */
            return 'RL';
        }

        // 2. Si pasa de 21 minutos, ahora siempre es RG (Ya no hay falta por tiempo excesivo)
        /* COMENTADO: Lógica antigua que marcaba falta después de 31 minutos
        if ($diferenciaMinutos > 21 && $diferenciaMinutos <= 31) return 'RG';
        return 'F';
        */
        return 'RG';
    }
}