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
                    // CAMBIO: Ahora bloqueamos si es 'F' (Falta) O 'RG' (Retardo Grave)
                    if ($dia['calificacion'] === 'F' || $dia['calificacion'] === 'RG') {
                        $diasMalos[] = Carbon::parse($dia['dia'])->day;
                    }
                }

                if (!empty($diasMalos)) {
                    $resumenFaltas[$m] = $diasMalos;
                }
            }
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
            
            $permisosRaw = method_exists($this->repository, 'getPermissions') 
                ? $this->repository->getPermissions($empleadoId, $startOfMonth, $endOfMonth) 
                : [];

            if (empty($registrosRaw) && empty($permisosRaw)) {
                return [
                    'horario' => null, 
                    'registros' => [],
                    'department_name'=> 'Sin area'
                    ];
            }

            $registrosProcesados = $this->transformarRegistros($registrosRaw, $holidaysRaw, $permisosRaw, $startOfMonth, $endOfMonth);

            $departmentName = $registrosRaw[0]->department_name ?? 'Sin departamento';

            $horarioTexto = 'Sin horario';
            if (isset($registrosRaw[0]) && $registrosRaw[0]->in_time && $registrosRaw[0]->duration) {
                try {
                    $startTime = Carbon::createFromFormat('H:i:s', $registrosRaw[0]->in_time);
                    $endTime = (clone $startTime)->addMinutes($registrosRaw[0]->duration);
                    $horarioTexto = $startTime->format('H:i:s') . ' A ' . $endTime->format('H:i:s');
                } catch (Exception $e) {
                    $horarioTexto = $registrosRaw[0]->in_time;
                }
            }
            //error_log($departmentName);

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

    // --- LÓGICA PRIVADA ---

    private function transformarRegistros($registros, $holidays, $permisos, $startDate, $endDate)
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $retardosLevesPrevios = 0;
        $resultados = [];

        for ($date = $start; $date->lte($end); $date->addDay()) {
            $fechaActualStr = $date->format('Y-m-d');
            
            $registroDia = null;
            foreach ($registros as $reg) {
                if (str_starts_with($reg->att_date, $fechaActualStr)) {
                    $registroDia = $reg;
                    break;
                }
            }

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

                        if ($date->isWeekend()) {
                $resultados[] = $this->crearFila($fechaActualStr, null, 'DESC', '');
                continue;
            }

            if ($permisoDia) {
                $resultados[] = $this->crearFila($fechaActualStr, $registroDia, 'J', $permisoDia->reason ?? 'Permiso');
                continue;
            }



            if (!$registroDia || ($holidayDia && isset($registroDia->enable_holiday) && $registroDia->enable_holiday === true)) {
                $resultados[] = $this->crearFila($fechaActualStr, null, 'DESC', $holidayDia ? $holidayDia->alias : '');
                continue;
            }

            if (!$registroDia) {
                $resultados[] = $this->crearFila($fechaActualStr, null, 'F', '');
                continue;
            }

            $calificacion = $this->evaluarRetardo($registroDia, $retardosLevesPrevios);
            if ($calificacion === 'RL') $retardosLevesPrevios++;
            if ($retardosLevesPrevios >= 4) {
                $calificacion = 'RG';
                $retardosLevesPrevios = 0;
            }
            
            // CAMBIO: Si es F o RG pero tiene justificación, pasa a 'J'
            if (($calificacion === 'F' || $calificacion === 'RG') && !empty($registroDia->apply_reason)) {
                $calificacion = 'J';
            }

            $resultados[] = $this->crearFila($fechaActualStr, $registroDia, $calificacion, $registroDia->apply_reason ?? '');
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
        $fechaCheckIn = Carbon::parse($registro->check_in)->format('Y-m-d');
        $horaEntradaEstandar = Carbon::parse($fechaCheckIn . ' ' . $registro->in_time);
        $horaRealEntrada = Carbon::parse($registro->clock_in);
        
        $diferenciaMinutos = $horaEntradaEstandar->diffInMinutes($horaRealEntrada, false);
        
        $tolerance = $registro->allow_late - 1;

        if ($diferenciaMinutos <= $tolerance) return 'OK';
        if ($diferenciaMinutos > $tolerance && $diferenciaMinutos <= 21) return ($retardosLevesPrevios >= 4) ? 'RG' : 'RL';
        if ($diferenciaMinutos > 21 && $diferenciaMinutos <= 31) return 'RG';
        return 'F';
    }
}