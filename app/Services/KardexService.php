<?php

namespace App\Services;

use App\Repositories\KardexRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Configuracion;
use Exception;

class KardexService
{
    protected $repository;

    public function __construct(KardexRepository $repository)
    {
        $this->repository = $repository;
    }

    public function generarKardex(array $filtros)
    {
        $empleadosPaginados = $this->repository->getEmpleadosPaginados($filtros);
        $datosProcesados = $this->procesarBloqueEmpleados($empleadosPaginados->items(), $filtros);

        return [
            'datosKardex' => $datosProcesados,
            'paginador' => $empleadosPaginados,
            'listaNominas' => $this->repository->getNominas(),
            'catalogoPermisos' => $this->repository->getCatalogoPermisos(),
        ];
    }

    public function generarKardexExport(array $filtros)
    {
        $empleadosTodos = $this->repository->getEmpleadosTodos($filtros);
        return $this->procesarBloqueEmpleados($empleadosTodos, $filtros);
    }

    public function obtenerReporteMensualEmpleado($empleado, $mes, $ano)
    {
        $fechaBase = Carbon::createFromDate($ano, $mes, 1);
        $diasTotalesDelMes = $fechaBase->daysInMonth;
        $fechaInicioMes = $fechaBase->copy()->startOfDay();
        $fechaFinMes = $fechaBase->copy()->endOfMonth()->endOfDay();

        $payloadData = $this->repository->getPayloadData([$empleado->id], $fechaInicioMes, $fechaFinMes);
        $permisos = $this->repository->getPermisos([$empleado->id], $fechaInicioMes, $fechaFinMes);
        $festivos = $this->repository->getDiasFestivos($fechaInicioMes, $fechaFinMes);

        $resultados = $this->procesarLogicaKardex([$empleado], $payloadData, $permisos, $festivos, $mes, $ano, 1, $diasTotalesDelMes);
        return $resultados[0];
    }

    private function procesarBloqueEmpleados($empleados, $filtros)
    {
        $fechaBase = Carbon::createFromDate((int)$filtros['ano'], (int)$filtros['mes'], 1);
        $diasTotalesDelMes = $fechaBase->daysInMonth;
        $diaInicio = ((int)$filtros['quincena'] == 2) ? 16 : 1;
        $diaFin = ((int)$filtros['quincena'] == 1) ? 15 : $diasTotalesDelMes;

        $fechaInicioMes = $fechaBase->copy()->day($diaInicio)->startOfDay();
        $fechaFinMes = $fechaBase->copy()->day($diaFin)->endOfMonth()->endOfDay();

        $empleadoIDs = collect($empleados)->pluck('id')->toArray();
        
        $payloadData = collect(); $permisos = collect(); $festivos = collect();

        if (count($empleadoIDs) > 0) {
            $payloadData = $this->repository->getPayloadData($empleadoIDs, $fechaInicioMes, $fechaFinMes);
            $permisos = $this->repository->getPermisos($empleadoIDs, $fechaInicioMes, $fechaFinMes);
            $festivos = $this->repository->getDiasFestivos($fechaInicioMes, $fechaFinMes);
        }

        return $this->procesarLogicaKardex($empleados, $payloadData, $permisos, $festivos, (int)$filtros['mes'], (int)$filtros['ano'], $diaInicio, $diaFin);
    }

    private function procesarLogicaKardex($empleados, $payloadData, $permisos, $festivos, $mes, $ano, $diaInicio, $diaFin)
    {
        try {
            $reglas = Configuracion::getAllRules();
        } catch (\Throwable $th) {
            // Reglas por defecto en caso de falla
            $reglas = ['tolerancia_entrada' => 10, 'limite_retardo_leve' => 15, 'conteo_rl_para_rg' => 4];
        }

        $filasDelKardex = [];

        foreach ($empleados as $empleado) {
            // CONTADORES DEL TRABAJO
            $contadores = ['rg' => 0, 'rl' => 0, 'j' => 0, 'f' => 0, 'omisiones' => 0];
            $retardosLevesAcumulados = 0;
            $limiteConversion = $reglas['conteo_rl_para_rg'] ?? 4;
            
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

                if ($fechaActual->greaterThanOrEqualTo(Carbon::today()) || ($fechaContratacion && $fechaActual->isBefore($fechaContratacion))) {
                    $filaEmpleado['incidencias_diarias'][$dia] = null;
                    continue; 
                }

                $esFestivo = $festivos->contains(fn($h) => str_starts_with($h->start_date, $fechaString));
                $festivoInfo = $esFestivo ? $festivos->first(fn($h) => str_starts_with($h->start_date, $fechaString)) : null;
                $permiso = $this->buscarPermiso($permisosParaEmpleado, $fechaActual);
                $payloadDia = $payloadParaEmpleado->firstWhere('att_date', $fechaString);

                // Estructura rica para la vista
                $incidencia = [
                    'calificacion' => '', 'checkin' => '', 'checkout' => '', 'observaciones' => '', 'nombre_permiso' => ''
                ];

                if ($fechaActual->isWeekend()) {
                    $incidencia['calificacion'] = 'DESC';
                }
                else if ($esFestivo && (!$payloadDia || (!$payloadDia->clock_in && !$payloadDia->clock_out))) {
                    $incidencia['calificacion'] = 'J'; 
                    $incidencia['observaciones'] = $festivoInfo->alias ?? 'Día Festivo';
                    $incidencia['nombre_permiso'] = 'FESTIVO';
                    $contadores['j']++;
                }
                else if (!$payloadDia) {
                    $incidencia['calificacion'] = 'F';
                    $contadores['f']++;
                }
                else {
                    $this->procesarHuellas($payloadDia);

                    $incidencia['checkin'] = $payloadDia->clock_in ? Carbon::parse($payloadDia->clock_in)->format('H:i:s') : '';
                    $incidencia['checkout'] = $payloadDia->clock_out ? Carbon::parse($payloadDia->clock_out)->format('H:i:s') : '';

                    $incidenciaAMostrar = !empty(trim($payloadDia->nombre_permiso ?? '')) ? $payloadDia->motivo_permiso : null;

                    // Si hay justificación en BioTime (Permisos)
                    if ($incidenciaAMostrar || $permiso) {
                        if ($payloadDia->clock_in && $incidenciaAMostrar) {
                            $entradaOficial = Carbon::parse($payloadDia->att_date . ' ' . $payloadDia->in_time);
                            $entradaReal = Carbon::parse($payloadDia->clock_in);
                            if ($entradaOficial->diffInMinutes($entradaReal, false) > ($reglas['limite_retardo_leve'] ?? 15)) {
                                $payloadDia->clock_in = null;
                            }
                        }
                        $incidencia['calificacion'] = 'J';
                        $incidencia['observaciones'] = $incidenciaAMostrar ?? 'Justificado';
                        $incidencia['nombre_permiso'] = $payloadDia->nombre_permiso ?? ($permiso->report_symbol ?? 'Permiso');
                        $contadores['j']++;
                    }
                    else if (!$payloadDia->clock_in && !$payloadDia->clock_out && empty($payloadDia->timetable_name)) {
                        $incidencia['calificacion'] = 'F';
                        $incidencia['observaciones'] = 'Sin horario asignado';
                        $contadores['f']++;
                    }
                    else if (!$payloadDia->clock_in || !$payloadDia->clock_out) {
                        $incidencia['calificacion'] = 'F';
                        $contadores['f']++;
                        if ($payloadDia->clock_in || $payloadDia->clock_out) {
                            $contadores['omisiones']++;
                            $incidencia['observaciones'] = !$payloadDia->clock_in ? 'Falta Entrada' : 'Falta Salida';
                        }
                    } 
                    else {
                        $calif = $this->evaluarRetardo($payloadDia, $reglas);
                        
                        if ($calif === 'RL') {
                            $retardosLevesAcumulados++;
                            if ($retardosLevesAcumulados >= $limiteConversion) {
                                $calif = 'RG';
                                $retardosLevesAcumulados = 0;
                                $incidencia['observaciones'] = 'Acumulación de ' . $limiteConversion . ' Retardos Leves';
                            }
                        }
                        
                        $incidencia['calificacion'] = $calif;

                        if ($calif === 'RL') $contadores['rl']++;
                        if ($calif === 'RG') $contadores['rg']++;
                    }
                }
                
                $filaEmpleado['incidencias_diarias'][$dia] = $incidencia;
            }

            $filaEmpleado['total_rg'] = $contadores['rg'];
            $filaEmpleado['total_rl'] = $contadores['rl'];
            $filaEmpleado['total_j'] = $contadores['j'];
            $filaEmpleado['total_f'] = $contadores['f'];
            $filaEmpleado['total_omisiones'] = $contadores['omisiones'];

            $filasDelKardex[] = $filaEmpleado;
        }
        return $filasDelKardex;
    }

    private function procesarHuellas($reg)
    {
        if (empty($reg->in_time) || empty($reg->all_punches)) return;
        $reg->clock_in = null; $reg->clock_out = null;
        $fechaStr = Carbon::parse($reg->att_date)->format('Y-m-d');
        
        $date = Carbon::parse($reg->att_date);
        $inicioPrimavera = Carbon::parse("first sunday of april $date->year");
        $finOtono = Carbon::parse("last sunday of october $date->year");
        $esVerano = $date->greaterThanOrEqualTo($inicioPrimavera) && $date->lessThan($finOtono);
        $horasAjuste = $esVerano ? 1 : 0;

        $duracionMinutos = $reg->duration ?? 480;
        $targetIn = Carbon::parse($fechaStr . ' ' . $reg->in_time);
        $targetOut = (clone $targetIn)->addMinutes($duracionMinutos);

        $punchesRaw = array_filter(explode(',', $reg->all_punches ?? ''), fn($p) => !empty(trim($p)));
        $punchesAjustados = []; $lastAdded = null;
        foreach ($punchesRaw as $pStr) {
            try {
                $p = Carbon::parse(trim($pStr));
                if ($horasAjuste != 0) $p->addHours($horasAjuste);
                if (!$lastAdded || abs($p->diffInSeconds($lastAdded)) > 30) {
                    $punchesAjustados[] = $p; $lastAdded = $p;
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
            if ($bestOut->lessThan($targetOut)) {
                $reg->clock_out = null; 
            } else {
                $reg->clock_out = $bestOut->format('Y-m-d H:i:s');
            }
        }
    }

    private function evaluarRetardo($registro, $reglas)
    {
        if (!$registro->clock_in || !$registro->in_time) return 'OK';
        $fechaCheckIn = Carbon::parse($registro->att_date)->format('Y-m-d');
        $horaEntradaEstandar = Carbon::parse($fechaCheckIn . ' ' . $registro->in_time);
        $horaRealEntrada = Carbon::parse($registro->clock_in);
        
        $diferenciaMinutos = $horaEntradaEstandar->diffInMinutes($horaRealEntrada, false);
        
        $tolerancia = $reglas['tolerancia_entrada'] ?? 10;
        $limiteLeve = $reglas['limite_retardo_leve'] ?? 15;

        if ($diferenciaMinutos <= $tolerancia) return 'OK';
        if ($diferenciaMinutos > $tolerancia && $diferenciaMinutos <= $limiteLeve) return 'RL';
        return 'RG';
    }

    private function buscarPermiso($permisosEmpleado, $fechaActual) {
        foreach ($permisosEmpleado as $permiso) {
            $inicio = Carbon::parse($permiso->start_time)->startOfDay();
            $fin = Carbon::parse($permiso->end_time)->endOfDay();
            if ($fechaActual->between($inicio, $fin, true)) return $permiso;
        } return null;
    }
}