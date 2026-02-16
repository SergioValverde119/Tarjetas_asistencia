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
            Log::error('Error en TarjetaService@obtenerTodosLosEmpleados: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Busca los datos de un empleado en BioTime.
     */
    public function buscarEmpleadoPorBiotimeId($biotimeId)
    {
        if (! $biotimeId) {
            return null;
        }

        try {
            $allEmployees = $this->repository->getAllEmployees();
            foreach ($allEmployees as $emp) {
                if (strval($emp->id) === strval($biotimeId)) {
                    return $emp;
                }
            }
        } catch (Exception $e) {
            Log::error('Error en TarjetaService@buscarEmpleado: ' . $e->getMessage());
        }

        return null;
    }



    
    /**
     * NUEVA FUNCIÓN PARA SEMÁFORO DINÁMICO:
     * Verifica si existe algún bloqueo (F o RG) en un mes y año específicos.
     * Esta función es la que llama el controlador dentro del loop de los 12 meses.
     */
    public function tieneFaltasEnMes($empleadoId, $month, $year)
    {
        try {
            // Reutilizamos la lógica de obtención de datos que ya funciona
            $datos = $this->obtenerDatosPorMes($empleadoId, $month, $year);
            
            foreach ($datos['registros'] as $dia) {
                // Si encontramos una sola falta o retardo grave, el mes se bloquea
                if ($dia['calificacion'] === 'F' || $dia['calificacion'] === 'RG') {
                    return true; 
                }
            }
        } catch (Exception $e) {
            Log::error("Error verificando faltas en $month-$year: " . $e->getMessage());
        }
        return false;
    }

    /**
     * NUEVA FUNCIÓN PARA LISTADO INDIVIDUAL:
     * Devuelve solo los números de día que tienen falta en un mes/año.
     */
    public function obtenerFaltasEspecificas($empleadoId, $month, $year)
    {
        try {
            $datos = $this->obtenerDatosPorMes($empleadoId, $month, $year);
            $diasConFalta = [];
            
            foreach ($datos['registros'] as $dia) {
                if ($dia['calificacion'] === 'F' || $dia['calificacion'] === 'RG') {
                    $diasConFalta[] = Carbon::parse($dia['dia'])->day;
                }
            }
            return $diasConFalta;
        } catch (Exception $e) {
            return [];
        }
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
                if ($year == now()->year && $m > now()->month) {
                    break;
                }

                // Llamamos a obtenerDatosPorMes para usar exactamente la misma vara de medir
                $datosMes = $this->obtenerDatosPorMes($empleadoId, $m, $year);

                $diasMalos = [];
                foreach ($datosMes['registros'] as $dia) {
                    if ($dia['calificacion'] === 'F' || $dia['calificacion'] === 'RG') {
                        $diasMalos[] = Carbon::parse($dia['dia'])->day;
                    }
                }

                if (! empty($diasMalos)) {
                    $resumenFaltas[$m] = $diasMalos;
                }
            }
        } catch (Exception $e) {
            Log::error('Error calculando resumen anual: ' . $e->getMessage());
        }

        return $resumenFaltas;
    }


    private function procesarHuellas($reg)
    {
        // 1. Inicialización Total
        $reg->clock_in = null;
        $reg->clock_out = null;

        $fechaStr = Carbon::parse($reg->att_date)->format('Y-m-d');



        // if (empty($reg->all_punches) || empty($reg->in_time)) {
        //     error_log("DÍA SALTADO: No hay huellas o no tiene horario asignado.");
        //     return;
        // }

        // --- A. CÁLCULO DE AJUSTE DE HORARIO (DST) ---
        $date = Carbon::parse($reg->att_date);
        $inicioPrimavera = Carbon::parse("first sunday of april $date->year");
        $finOtono = Carbon::parse("last sunday of october $date->year");
        $esVerano = $date->greaterThanOrEqualTo($inicioPrimavera) && $date->lessThan($finOtono);
        $horasAjuste = $esVerano ? 1 : 0;

        // --- B. DEFINIR HORARIOS OBJETIVO ---
        $duracionMinutos = $reg->duration ?? 480;
        $targetIn = Carbon::parse($fechaStr . ' ' . $reg->in_time);
        $targetOut = (clone $targetIn)->addMinutes($duracionMinutos);



        // --- C. PROCESAR Y AJUSTAR TODAS LAS HUELLAS ---
        $punchesRaw = array_filter(explode(',', $reg->all_punches), fn($p) => !empty(trim($p)));
        $punchesAjustados = [];
        $lastAdded = null;

        foreach ($punchesRaw as $pStr) {
            try {
                $p = Carbon::parse(trim($pStr));

                if ($horasAjuste != 0) {
                    $p->addHours($horasAjuste);
                }

                if (!$lastAdded || abs($p->diffInSeconds($lastAdded)) > 30) {
                    $punchesAjustados[] = $p;
                    $lastAdded = $p;
                }
            } catch (Exception $e) {
                error_log("ERROR PARSEANDO HUELLA: [{$pStr}]");
            }
        }



        // --- D. BUSCAR CHECADAS ÓPTIMAS (ENTRADA Y SALIDA EN UN SOLO LOOP) ---
        $bestIn = null;
        $bestOut = null;
        $minDistIn = 999999;
        $minDistOut = 999999;


        foreach ($punchesAjustados as $punchStr) {
             $punch = Carbon::parse($punchStr);
            $distIn = abs($targetIn->diffInMinutes($punch, false));
            $outDiff = $targetOut->diffInMinutes($punch, false);
            $distOut = abs($outDiff);


            if ($distIn < $distOut) {
                // Más cercano a hora de entrada
                if ($distIn < $minDistIn) {
                    $minDistIn = $distIn;
                    $bestIn = $punch;
                }
            } else {
                // Más cercano a hora de salida
                // if ($distOut < $minDistOut) {
                //     $minDistOut = $distOut;
                //     $bestOut = $punch;
                // }
                // --- AGREGADO: Lógica de validación para salida ---

                // 2. CANDIDATO A SALIDA (Lógica basada en minutos de diferencia)
                
                // ALTERNATIVA: En lugar de greaterThanOrEqualTo, usamos la diferencia en minutos.
                // Si la diferencia es 0 o positiva, significa que checó a tiempo o después.
                $isPunchValid = $outDiff >= 0;

                // Evaluamos si la que ya teníamos guardada era válida
                $isCurrentBestValid = false;
                if ($bestOut) {
                    $currentOutDiff = $targetOut->diffInMinutes($bestOut, false);
                    $isCurrentBestValid = $currentOutDiff >= 0;
                }

                // REGLA DE ORO: Reemplazamos la salida guardada si...
                if (
                    // A. No teníamos ninguna salida guardada todavía
                    !$bestOut || 
                    // B. El nuevo registro es VÁLIDO y el que teníamos era TEMPRANO (Prioridad de cumplimiento)
                    ($isPunchValid && !$isCurrentBestValid) ||
                    // C. Ambos son del mismo tipo (los dos válidos o los dos tempranos) pero el nuevo está más cerca
                    ($isPunchValid === $isCurrentBestValid && $distOut < $minDistOut)
                ) {
                    $minDistOut = $distOut;
                    $bestOut = $punch;
                }
                
            }
        }


        // --- E. REGLA DE limite de tiempo en minutos ---
        $umbral = 30;

        if ($bestIn && $minDistIn <= $umbral) {
            $reg->clock_in = $bestIn->format('Y-m-d H:i:s');
        }

        if ($bestOut && $minDistOut <= $umbral) {
            $reg->clock_out = $bestOut->format('Y-m-d H:i:s');
        }

        if ($bestOut && $minDistOut <= $umbral) {
            
            
            if ($bestOut->lessThan($targetOut)) {
                $reg->clock_out = null; 
            } else {
                $reg->clock_out = $bestOut->format('Y-m-d H:i:s');
            }
        }
        

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

            // AGREGADO: Verificamos solo registrosRaw
            if (empty($registrosRaw)) {
                return [
                    'horario' => null,
                    'registros' => [],
                    'department_name' => 'Sin area',
                ];
            }

            // AGREGADO: Llamamos al transformador solo con lo que necesitamos
            $registrosProcesados = $this->transformarRegistros($registrosRaw, $holidaysRaw, $startOfMonth, $endOfMonth);

            $departmentName = $registrosRaw[0]->department_name ?? 'Sin departamento';

            $horarioTexto = 'Sin horario';

            // AGREGADO: Usamos off_time que viene del Repositorio (Garantiza horario aunque sea descanso el día 1)
            if (isset($registrosRaw[0]) && $registrosRaw[0]->in_time && $registrosRaw[0]->off_time) {
                $horarioTexto = $registrosRaw[0]->in_time . ' A ' . $registrosRaw[0]->off_time;
            }

            return [
                'horario' => $horarioTexto,
                'department_name' => $departmentName,
                'registros' => $registrosProcesados,
            ];
        } catch (Exception $e) {
            Log::error('Error en TarjetaService@obtenerDatosPorMes: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * LÓGICA PRIVADA PARA TRANSFORMAR REGISTROS
     * RESTAURADO: Parámetros originales ($registros, $holidays, $permisos, $startDate, $endDate)
     */
    private function transformarRegistros($registros, $holidays, $startDate, $endDate)
    {
        
        $resultados = [];

        foreach ($registros as $reg) {
            $date = Carbon::parse($reg->att_date);
            $fechaActualStr = $date->format('Y-m-d');

            $this->procesarHuellas($reg);
            // AGREGADO: PRIORIDAD 1: Si el SQL ya encontró una incidencia para este día, la usamos.
            // Esto resuelve el problema de Sergio (ID 14154) donde se encimaban motivos.


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


            // AGREGADO: Lógica de descanso si no hay huellas y es festivo
            // AGREGADO: Si el SQL trae motivo, es Justificado ('J')
            // if (! $reg->clock_in && ! $reg->clock_out && (! $reg->timetable_name || ($holidayDia && isset($reg->enable_holiday) && $reg->enable_holiday === true))) {
            //     $resultados[] = $this->crearFila($fechaActualStr, null, 'J', $holidayDia ? $holidayDia->alias : 'No hay datos');
            //     continue;
            // }

            if (!$reg->clock_in && !$reg->clock_out && $holidayDia && ($reg->enable_holiday ?? false)) {
                $resultados[] = $this->crearFila($fechaActualStr, null, 'J', $holidayDia->alias);
                continue;
            }

            if (!$reg->clock_in && !$reg->clock_out && !$reg->timetable_name) {
                $resultados[] = $this->crearFila($fechaActualStr, null, 'F', 'Sin horario asignado');
                continue;
            }
            
            $incidenciaAMostrar = ! empty(trim($reg->nombre_permiso ?? '')) ? $reg->motivo_permiso : null;
            if ($incidenciaAMostrar) {
                if ($reg->clock_in) {
                    $entradaOficialRef = Carbon::parse($reg->att_date . ' ' . $reg->in_time);
                    $entradaRealRef = Carbon::parse($reg->clock_in);
                    if ($entradaOficialRef->diffInMinutes($entradaRealRef, false) > 21) 
                        {
                        $reg->clock_in = null;
                    }
                }
                $resultados[] = $this->crearFila($fechaActualStr, $reg, 'J', $incidenciaAMostrar);
                continue;
            }

            // AGREGADO: Si no hay entrada del reloj (y no hubo motivo arriba), es Falta
            if (! $reg->clock_in || ! $reg->clock_out) {
                $resultados[] = $this->crearFila($fechaActualStr, $reg, 'F', '');
                continue;
            }

            // --- AGREGADO: NUEVA LÓGICA DE CLASIFICACIÓN SIN ACUMULACIÓN ---
            $calificacion = $this->evaluarRetardo($reg);

            $resultados[] = $this->crearFila($fechaActualStr, $reg, $calificacion, $reg->nombre_permiso ?? '');
        }

        return $resultados;
    }

    private function crearFila($fecha, $registro, $calificacion, $obs)
    {
        return [
            'dia' => $fecha,
            'checkin' => ($registro && $registro->clock_in) ? Carbon::parse($registro->clock_in)->format('H:i:s') : '',
            'checkout' => ($registro && $registro->clock_out) ? Carbon::parse($registro->clock_out)->format('H:i:s') : '',
            'calificacion' => $calificacion,
            'observaciones' => $obs,
        ];
    }

    private function evaluarRetardo($registro)
    {
        // RESTAURADO: Lógica basada en check_in como el original
        $fechaCheckIn = Carbon::parse($registro->att_date)->format('Y-m-d');
        $horaEntradaEstandar = Carbon::parse($fechaCheckIn . ' ' . $registro->in_time);
        $horaRealEntrada = Carbon::parse($registro->clock_in);
        $diferenciaMinutos = $horaEntradaEstandar->diffInMinutes($horaRealEntrada, false);

        $tolerancia = 11;

        if ($diferenciaMinutos <= $tolerancia) {
            return 'OK';
        }

        // --- AGREGADO: CLASIFICACIÓN DIRECTA SIN LÍMITE DE FALTA POR TIEMPO ---
        if ($diferenciaMinutos > $tolerancia && $diferenciaMinutos <= 21) {
            return 'RL';
        }
        return 'RG';
    }
}
