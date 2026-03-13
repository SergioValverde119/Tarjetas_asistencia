<?php

namespace App\Services;

use App\Repositories\KardexRepository;
use Carbon\Carbon;
use App\Models\Configuracion;

/**
 * Servicio de Kárdex (Motor Unificado)
 * Procesa la lógica de asistencia vinculando horarios y huellas.
 * Primeramente Jehová Dios y Jesús Rey.
 */
class KardexService
{
    protected $repository;

    public function __construct(KardexRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Genera la data para la tabla principal del Kárdex.
     */
    public function generarKardex(array $filtros)
    {
        $empleadosP = $this->repository->getEmpleadosPaginados($filtros);
        
        return [
            'datosKardex' => $this->procesarBloqueEmpleados($empleadosP->items(), $filtros),
            'paginador' => $empleadosP,
            'listaNominas' => $this->repository->getNominas(),
            'catalogoPermisos' => $this->repository->getCatalogoPermisos(),
        ];
    }

    /**
     * Obtiene el reporte procesado para la vista individual.
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

    private function procesarBloqueEmpleados($empleados, $filtros)
    {
        $fBase = Carbon::createFromDate((int)$filtros['ano'], (int)$filtros['mes'], 1);
        $diaI = ((int)$filtros['quincena'] == 2) ? 16 : 1;
        $diaF = ((int)$filtros['quincena'] == 1) ? 15 : $fBase->daysInMonth;

        $payload = $this->repository->getPayloadData(
            collect($empleados)->pluck('id')->toArray(), 
            $fBase->copy()->day($diaI), 
            $fBase->copy()->day($diaF)
        );
        
        return $this->procesarLogicaKardex($empleados, $payload, (int)$filtros['mes'], (int)$filtros['ano'], $diaI, $diaF);
    }

    /**
     * Procesa la lógica de cada día para un grupo de empleados.
     */
    private function procesarLogicaKardex($empleados, $payload, $mes, $ano, $diaI, $diaF)
    {
        $reglas = Configuracion::getAllRules();
        $resultados = [];

        foreach ($empleados as $emp) {
            $cont = ['rg' => 0, 'rl' => 0, 'j' => 0, 'f' => 0, 'om' => 0];
            $rlAcum = 0;
            $dataEmp = $payload->get($emp->id) ?? collect();

            // --- DETECCIÓN DEL NOMBRE DEL HORARIO ---
            // Buscamos en el payload si algún día tiene el shift_name
            $shiftName = 'Sin horario';
            $primerDiaConDatos = $dataEmp->first(fn($d) => !empty($d->shift_name));
            if ($primerDiaConDatos) {
                $shiftName = $primerDiaConDatos->shift_name;
            } else {
                // Si el mes no tiene asignación, rescatamos el último histórico
                $historial = $this->repository->getUltimoHorarioAsignado($emp->id);
                if ($historial) $shiftName = $historial->nombre;
            }

            $fila = [
                'id' => $emp->id, 
                'emp_code' => $emp->emp_code, 
                'nombre' => $emp->first_name.' '.$emp->last_name, 
                'nomina' => $emp->nomina, 
                'horario_nombre' => $shiftName, // Propiedad vital para el front
                'incidencias_diarias' => []
            ];

            for ($d = $diaI; $d <= $diaF; $d++) {
                $fActual = Carbon::createFromDate($ano, $mes, $d)->startOfDay();
                $fStr = $fActual->toDateString();
                $reg = $dataEmp->firstWhere('att_date', $fStr);

                $inc = ['calificacion' => '', 'checkin' => '', 'checkout' => '', 'observaciones' => '', 'nombre_permiso' => ''];

                if ($fActual->isWeekend()) {
                    $inc['calificacion'] = 'DESC';
                } elseif ($reg) {
                    $this->interpretarHuellas($reg);
                    $inc['checkin'] = $reg->clock_in ? substr($reg->clock_in, 11, 5) : '';
                    $inc['checkout'] = $reg->clock_out ? substr($reg->clock_out, 11, 5) : '';

                    if (!empty($reg->nombre_permiso)) {
                        $inc['calificacion'] = 'J';
                        $inc['observaciones'] = $reg->motivo_permiso;
                        $inc['nombre_permiso'] = $reg->nombre_permiso;
                        $cont['j']++;
                    } elseif (!$reg->clock_in || !$reg->clock_out) {
                        $inc['calificacion'] = 'F';
                        $inc['observaciones'] = !$reg->clock_in ? 'Falta Entrada' : 'Falta Salida';
                        $cont['f']++; $cont['om']++;
                    } else {
                        $cal = $this->evaluar($reg, $reglas);
                        if ($cal === 'RL') {
                            $rlAcum++;
                            if ($rlAcum >= ($reglas['conteo_rl_para_rg'] ?? 4)) { $cal = 'RG'; $rlAcum = 0; }
                        }
                        $inc['calificacion'] = $cal;
                        if ($cal === 'RL') $cont['rl']++;
                        if ($cal === 'RG') $cont['rg']++;
                    }
                } else {
                    $inc['calificacion'] = 'F'; $cont['f']++;
                }
                $fila['incidencias_diarias'][$d] = $inc;
            }
            $fila = array_merge($fila, [
                'total_rg' => $cont['rg'], 'total_rl' => $cont['rl'], 
                'total_j' => $cont['j'], 'total_f' => $cont['f'], 
                'total_omisiones' => $cont['om']
            ]);
            $resultados[] = $fila;
        }
        return $resultados;
    }

    private function interpretarHuellas($r) {
        $r->clock_in = null; $r->clock_out = null;
        if (empty($r->in_time) || empty($r->all_punches)) return;
        
        $tIn = Carbon::parse($r->att_date.' '.$r->in_time);
        $tOut = (clone $tIn)->addMinutes($r->duration ?? 480);
        $punches = array_filter(explode(',', $r->all_punches));
        
        $bestIn = null; $bestOut = null; $minIn = 999; $minOut = 999;
        foreach ($punches as $pStr) {
            $p = Carbon::parse($pStr);
            $dIn = abs($tIn->diffInMinutes($p, false));
            $dOut = abs($tOut->diffInMinutes($p, false));
            if ($dIn < $dOut && $dIn <= 30) { if ($dIn < $minIn) { $minIn = $dIn; $bestIn = $p; } }
            elseif ($dOut <= 31) { if ($dOut < $minOut) { $minOut = $dOut; $bestOut = $p; } }
        }
        $r->clock_in = $bestIn?->toDateTimeString();
        $r->clock_out = ($bestOut && $bestOut >= $tOut) ? $bestOut->toDateTimeString() : null;
    }

    private function evaluar($r, $rules) {
        if (!$r->clock_in) return 'F';
        $dif = Carbon::parse($r->att_date.' '.$r->in_time)->diffInMinutes(Carbon::parse($r->clock_in), false);
        if ($dif <= ($rules['tolerancia_entrada'] ?? 10)) return 'OK';
        if ($dif <= ($rules['limite_retardo_leve'] ?? 15)) return 'RL';
        return 'RG';
    }
}