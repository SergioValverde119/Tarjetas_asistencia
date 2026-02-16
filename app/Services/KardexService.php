<?php

namespace App\Services;

use App\Repositories\KardexRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class KardexService
{
    protected $repository;

    public function __construct(KardexRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Orquesta la generación completa del reporte para la vista.
     */
    public function generarKardex(array $filtros)
    {
        // 1. Calcular fechas
        $fechaBase = Carbon::createFromDate((int)$filtros['ano'], (int)$filtros['mes'], 1);
        $diasTotalesDelMes = $fechaBase->daysInMonth;
        $diaInicio = ((int)$filtros['quincena'] == 2) ? 16 : 1;
        $diaFin = ((int)$filtros['quincena'] == 1) ? 15 : $diasTotalesDelMes;

        $fechaInicioMes = $fechaBase->copy()->day($diaInicio)->startOfDay();
        // Nota: Traemos hasta fin de mes para asegurar datos completos, aunque solo mostremos la quincena
        $fechaFinMes = $fechaBase->copy()->day($diaFin)->endOfMonth()->endOfDay(); 

        // 2. Obtener Empleados (Paginados)
        $empleadosPaginados = $this->repository->getEmpleadosPaginados($filtros);
        $empleadoIDs = $empleadosPaginados->pluck('id')->toArray();

        // 3. Obtener Datos Masivos (Payload, Permisos, Festivos)
        $payloadData = collect();
        $permisos = collect();
        $festivos = collect();

        if (count($empleadoIDs) > 0) {
            $payloadData = $this->repository->getPayloadData($empleadoIDs, $fechaInicioMes, $fechaFinMes);
            $permisos = $this->repository->getPermisos($empleadoIDs, $fechaInicioMes, $fechaFinMes);
            $festivos = $this->repository->getDiasFestivos($fechaInicioMes, $fechaFinMes);
        }

        // 4. Procesar Lógica (Aquí aplicamos las reglas de negocio)
        $datosProcesados = $this->procesarLogicaKardex(
            $empleadosPaginados->items(),
            $payloadData,
            $permisos,
            $festivos,
            (int)$filtros['mes'],
            (int)$filtros['ano'],
            $diaInicio,
            $diaFin
        );

        // 5. Retornar todo listo para el Controlador
        return [
            'datosKardex' => $datosProcesados,
            'paginador' => $empleadosPaginados,
            // Catálogos auxiliares que necesita la vista
            'listaNominas' => $this->repository->getNominas(),
            'catalogoPermisos' => $this->repository->getCatalogoPermisos(),
        ];
    }

    /**
     * Lógica Maestra: Itera empleados y días aplicando reglas.
     */
    private function procesarLogicaKardex($empleados, $payloadData, $permisos, $festivos, $mes, $ano, $diaInicio, $diaFin)
    {
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

                // Filtros de fecha (Futuro o antes de contrato)
                if ($fechaActual->greaterThanOrEqualTo(Carbon::today()) || ($fechaContratacion && $fechaActual->isBefore($fechaContratacion))) {
                    $filaEmpleado['incidencias_diarias'][$dia] = null;
                    continue; 
                }

                $esFestivo = $festivos->contains(fn($h) => str_starts_with($h->start_date, $fechaString));
                $permiso = $this->buscarPermiso($permisosParaEmpleado, $fechaActual);
                $payloadDia = $payloadParaEmpleado->firstWhere('att_date', $fechaString);

                $incidencia = "";

                // --- ÁRBOL DE DECISIÓN ---

                if ($fechaActual->isWeekend()) {
                    $incidencia = "DESC";
                }
                else if ($esFestivo && (!$payloadDia || (!$payloadDia->clock_in && !$payloadDia->clock_out))) {
                    $incidencia = "J"; 
                }
                else if ($permiso) {
                    $incidencia = $permiso->report_symbol; 
                }
                else if (!$payloadDia) {
                    $incidencia = "Falto";
                    $contadores['faltas']++;
                }
                else {
                    // *** REGLA DE SALIDA ANTICIPADA Y TOLERANCIAS ***
                    $this->procesarHuellas($payloadDia);

                    // Reglas de Asistencia
                    if (!$payloadDia->clock_in && !$payloadDia->clock_out && empty($payloadDia->timetable_name)) {
                        $incidencia = "Falto";
                        $contadores['faltas']++;
                    }
                    else if ($payloadDia->clock_in && $payloadDia->clock_out) {
                        $eval = $this->evaluarRetardo($payloadDia);
                        if ($eval === 'OK') {
                            $incidencia = "OK";
                        } else {
                            $incidencia = "R";
                            $contadores['retardos']++;
                        }
                    } else {
                        if (!$payloadDia->clock_in && !$payloadDia->clock_out) {
                            $incidencia = "Falto";
                            $contadores['faltas']++;
                        } else {
                            // Aquí caen las "Salidas Anticipadas" borradas o checadas faltantes
                            $incidencia = !$payloadDia->clock_in ? "S/E" : "S/S";
                            $contadores['omisiones']++;
                        }
                    }
                }
                
                $filaEmpleado['incidencias_diarias'][$dia] = $incidencia;
            }

            $filaEmpleado['total_faltas'] = $contadores['faltas'];
            $filaEmpleado['total_retardos'] = $contadores['retardos'];
            $filaEmpleado['total_omisiones'] = $contadores['omisiones'];

            $filasDelKardex[] = $filaEmpleado;
        }
        return $filasDelKardex;
    }

    // --- FUNCIONES PRIVADAS (REGLAS DE NEGOCIO) ---

    private function procesarHuellas($reg)
    {
        if (empty($reg->in_time)) return;

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

        $umbral = 30; 

        if ($bestIn && $minDistIn <= $umbral) $reg->clock_in = $bestIn->format('Y-m-d H:i:s');
        
        if ($bestOut && $minDistOut <= $umbral) {
            // REGLA: Si salió antes, borramos la salida para que marque incidencia
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