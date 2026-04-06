<?php

namespace App\Services;

use App\Repositories\KardexRepository;
use Carbon\Carbon;
use App\Models\Configuracion;

class KardexService
{
    protected $repository;

    public function __construct(KardexRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Genera la información masiva para la tabla general del Kárdex.
     */
    public function generarKardex(array $filtros)
    {
        // 1. Obtener los empleados paginados desde el repositorio
        $paginador = $this->repository->getEmpleadosPaginados($filtros);
        $empleados = $paginador->items();

        // 2. Determinar el rango de días a procesar (Quincena o Mes)
        $fBase = Carbon::createFromDate($filtros['ano'], $filtros['mes'], 1);
        $diaI = ($filtros['quincena'] == 2) ? 16 : 1;
        $diaFinMes = $fBase->daysInMonth;
        $diaF = ($filtros['quincena'] == 1) ? 15 : $diaFinMes;

        $fechaInicio = $fBase->copy()->day($diaI)->startOfDay();
        $fechaFin = $fBase->copy()->day($diaF)->endOfMonth();

        // 3. Obtener el "Payload" masivo (huellas, horarios y permisos)
        $empleadoIDs = collect($empleados)->pluck('id')->toArray();
        
        if (empty($empleadoIDs)) {
            return [
                'datosKardex' => [],
                'paginador' => $paginador,
                'listaNominas' => $this->repository->getNominas(),
                'catalogoPermisos' => $this->repository->getCatalogoPermisos(),
            ];
        }

        $payload = $this->repository->getPayloadData($empleadoIDs, $fechaInicio, $fechaFin);

        // 4. Procesar la lógica de negocio (asistencias, retardos, faltas)
        $datosKardex = $this->procesarLogicaKardex(
            $empleados, 
            $payload, 
            $filtros['mes'], 
            $filtros['ano'], 
            $diaI, 
            $diaF
        );

        // 5. Retornar el paquete completo para el controlador
        return [
            'datosKardex' => $datosKardex,
            'paginador' => $paginador,
            'listaNominas' => $this->repository->getNominas(),
            'catalogoPermisos' => $this->repository->getCatalogoPermisos(),
        ];
    }

    /**
     * Obtiene el reporte individual para el visor de empleado.
     */
    public function obtenerReporteMensualEmpleado($empleado, $mes, $ano)
    {
        $fBase = Carbon::createFromDate($ano, $mes, 1);
        $payload = $this->repository->getPayloadData([$empleado->id], $fBase->copy()->startOfDay(), $fBase->copy()->endOfMonth());
        
        $procesado = $this->procesarLogicaKardex(
            [$empleado], 
            $payload, 
            $mes, $ano, 1, $fBase->daysInMonth
        );

        return $procesado[0];
    }

    /**
     * Motor principal de cálculo de incidencias.
     */
    private function procesarLogicaKardex($empleados, $payload, $mes, $ano, $diaI, $diaF)
{
    $reglas = Configuracion::getAllRules();
    $festivos = $this->repository->getDiasFestivos(
        Carbon::createFromDate($ano, $mes, $diaI)->startOfDay(),
        Carbon::createFromDate($ano, $mes, $diaF)->endOfMonth()
    );
    $resultados = [];

    foreach ($empleados as $emp) {
        $cont = ['rg' => 0, 'rl' => 0, 'j' => 0, 'f' => 0, 'om' => 0];
        $retardosLevesAcumulados = 0;
        $limiteConversion = $reglas['conteo_rl_para_rg'] ?? 4;
        
        $dataEmp = $payload->get($emp->id) ?? collect();

        $fila = [
            'id' => $emp->id, 
            'emp_code' => $emp->emp_code, 
            'nombre' => $emp->first_name.' '.$emp->last_name, 
            'nomina' => $emp->nomina, 
            'incidencias_diarias' => []
        ];

        for ($d = $diaI; $d <= $diaF; $d++) {
            $fechaActual = Carbon::createFromDate($ano, $mes, $d)->startOfDay();
            $fechaActualStr = $fechaActual->toDateString();
            $reg = $dataEmp->firstWhere('att_date', $fechaActualStr);
            $holidayDia = $festivos->first(fn($h) => str_starts_with($h->start_date, $fechaActualStr));

            $incidencia = ['calificacion' => '', 'checkin' => '', 'checkout' => '', 'observaciones' => '', 'nombre_permiso' => ''];

            if ($fechaActual->isWeekend()) {
                $incidencia['calificacion'] = 'DESC';
            }
            else if ($reg) {
                $this->interpretarHuellas($reg);
                $incidencia['checkin'] = $reg->clock_in ? substr($reg->clock_in, 11, 5) : '';
                $incidencia['checkout'] = $reg->clock_out ? substr($reg->clock_out, 11, 5) : '';

                // 1. Festivo Prioritario
                if (!$reg->clock_in && !$reg->clock_out && $holidayDia && ($reg->enable_holiday ?? false)) {
                    $incidencia['calificacion'] = 'J';
                    $incidencia['observaciones'] = $holidayDia->alias;
                    $cont['j']++;
                }
                // 2. Justificante / Permiso
                else if (!empty(trim($reg->nombre_permiso ?? ''))) {
                    $incidencia['calificacion'] = 'J';
                    $incidencia['observaciones'] = $reg->motivo_permiso;
                    $incidencia['nombre_permiso'] = $reg->nombre_permiso;
                    $cont['j']++;
                }
                // 3. FALTA TOTAL (Si NO tiene entrada Y NO tiene salida)
                else if (!$reg->clock_in && !$reg->clock_out) {
                    $incidencia['calificacion'] = 'F';
                    $incidencia['observaciones'] = 'Inasistencia';
                    $cont['f']++; 
                }
                // 4. OMISIÓN (Si le falta UNA de las dos checadas)
                else if (!$reg->clock_in || !$reg->clock_out) {
                    $incidencia['calificacion'] = !$reg->clock_in ? 'S/E' : 'S/S';
                    $incidencia['observaciones'] = !$reg->clock_in ? 'Falta Entrada' : 'Falta Salida';
                    $cont['om']++; 
                }
                // 5. Asistencia Normal / Retardos (Tiene ambas checadas)
                else {
                    $calificacion = $this->evaluar($reg, $reglas);
                    
                    // Lógica de acumulación de Retardos Leves para convertirlos a Graves
                    if ($calificacion === 'RL') {
                        $retardosLevesAcumulados++;
                        if ($retardosLevesAcumulados >= $limiteConversion) {
                            $calificacion = 'RG';
                            $retardosLevesAcumulados = 0;
                        }
                    }
                    
                    $incidencia['calificacion'] = $calificacion;
                    
                    if ($calificacion === 'RL') $cont['rl']++;
                    if ($calificacion === 'RG') $cont['rg']++;
                    if ($calificacion === 'OK') { /* No suma a contadores de error */ }
                    if ($calificacion === 'F') $cont['f']++; 
                }
            } else {
                // FALTA TOTAL (Caso donde no hay registro alguno en el payload)
                $incidencia['calificacion'] = 'F';
                $incidencia['observaciones'] = 'Sin registro de jornada';
                $cont['f']++;
            }

            $fila['incidencias_diarias'][$d] = $incidencia;
        }

        // Asignar los totales calculados a la fila del empleado
        $fila = array_merge($fila, [
            'total_rg' => $cont['rg'], 
            'total_rl' => $cont['rl'], 
            'total_j' => $cont['j'], 
            'total_f' => $cont['f'], 
            'total_omisiones' => $cont['om']
        ]);
        $resultados[] = $fila;
    }
    return $resultados;
}

    /**
     * Analiza las huellas en bruto para encontrar la mejor entrada y salida.
     */
    private function interpretarHuellas($r) 
    {
        $r->clock_in = null; 
        $r->clock_out = null;
        
        if (empty($r->in_time) || empty($r->all_punches)) return;

        // --- CÁLCULO DE AJUSTE DE HORARIO (DST) ---
        $date = Carbon::parse($r->att_date);
        $inicioPrimavera = Carbon::parse("first sunday of april {$date->year}");
        $finOtono = Carbon::parse("last sunday of october {$date->year}");
        $esVerano = $date->greaterThanOrEqualTo($inicioPrimavera) && $date->lessThan($finOtono);
        $horasAjuste = $esVerano ? 1 : 0;

        $punches = array_filter(explode(',', $r->all_punches));
        $tIn = Carbon::parse($r->att_date.' '.$r->in_time);
        $tOut = (clone $tIn)->addMinutes($r->duration ?? 480);
        
        $bestIn = null; 
        $bestOut = null; 
        $minIn = 999; 
        $minOut = 999;

        foreach ($punches as $pStr) {
            $p = Carbon::parse(trim($pStr));

            // Aplicar el ajuste de hora si estamos en periodo de verano
            if ($horasAjuste > 0) {
                $p->addHours($horasAjuste);
            }

            $dIn = abs($tIn->diffInMinutes($p, false));
            $dOut = abs($tOut->diffInMinutes($p, false));
            
            if ($dIn < $dOut && $dIn <= 61) { 
                if ($dIn < $minIn) { 
                    $minIn = $dIn; 
                    $bestIn = $p; 
                } 
            } 
            elseif ($dOut <= 31) { 
                if ($dOut < $minOut) { 
                    $minOut = $dOut; 
                    $bestOut = $p; 
                } 
            }
        }
        
        $r->clock_in = $bestIn?->toDateTimeString();
        $r->clock_out = ($bestOut && $bestOut >= $tOut) ? $bestOut->toDateTimeString() : null;
    }

    /**
     * Aplica las reglas de tolerancia para calificar la asistencia.
     */
    private function evaluar($r, $rules) {
        if (!$r->clock_in) return 'F';
        $dif = Carbon::parse($r->att_date.' '.$r->in_time)->diffInMinutes(Carbon::parse($r->clock_in), false);
        if ($dif > ($rules['minutos_falta_automatica'] ?? 41)) return 'F';
        if ($dif <= ($rules['tolerancia_entrada'] ?? 10)) return 'OK';
        if ($dif <= ($rules['limite_retardo_leve'] ?? 15)) return 'RL';
        return 'RG';
    }

    // app/Services/KardexService.php
/**
 * Procesa el directorio estático para el Excel.
 */
public function generarDirectorioHorarios()
{
    $rawResults = $this->repository->getDirectorioEstaticoHorarios();
    $empleados = [];
    $diasTexto = [1 => 'Lun', 2 => 'Mar', 3 => 'Mie', 4 => 'Jue', 5 => 'Vie', 6 => 'Sab', 0 => 'Dom'];

    foreach ($rawResults as $row) {
        if (!isset($empleados[$row->id])) {
            $empleados[$row->id] = [
                'emp_code'  => $row->emp_code,
                'nombre'    => $row->first_name,
                'apellidos' => $row->last_name,
                'area'      => $row->area_nombre ?? 'Sin Área',
                'turno'     => $row->nombre_turno ?? 'SIN HORARIO',
                'entrada'   => '-',
                'salida'    => '-',
                'indices'   => []
            ];
        }

        if ($row->day_index !== null) {
            if ($empleados[$row->id]['entrada'] === '-') {
                $empleados[$row->id]['entrada'] = substr($row->in_time, 0, 5);
                $empleados[$row->id]['salida']  = substr($row->out_time, 0, 5);
            }
            $empleados[$row->id]['indices'][] = $row->day_index;
        }
    }

    foreach ($empleados as &$emp) {
        if (empty($emp['indices'])) {
            $emp['dias_laborales'] = 'N/A';
        } else {
            sort($emp['indices']);
            $nombres = array_map(fn($idx) => $diasTexto[$idx], array_unique($emp['indices']));
            $emp['dias_laborales'] = implode(', ', $nombres);
        }
        unset($emp['indices']); // Limpiar dato temporal
    }

    return array_values($empleados);
}
}