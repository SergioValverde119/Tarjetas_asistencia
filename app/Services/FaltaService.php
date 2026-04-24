<?php

namespace App\Services;

use App\Repositories\FaltaRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Servicio para el procesamiento lógico de Faltas.
 * Primeramente Jehová Dios y Jesús Rey.
 */
class FaltaService
{
    protected $repository;

    public function __construct(FaltaRepository $repository)
    {
        $this->repository = $repository;
    }

    public function procesarReporteFaltas($areaId, $empId, $startDate, $endDate, $exclude = [], $departmentId = null)
    {
        try {
            // 1. Normalización de la lista negra de nóminas
            $excludeList = array_map(fn($i) => trim((string)$i), (array)$exclude);

            $empleados = $this->repository->getEmpleadosParaMonitoreo($areaId, $empId, $exclude, $departmentId);
            $resultadoFinal = [];

            foreach ($empleados as $emp) {
                // --- CANDADO DE SEGURIDAD ABSOLUTO ---
                // Si la nómina del empleado está en la lista de exclusión, lo expulsamos.
                // Esto garantiza que el usuario Sergio Axel (1206977) nunca aparezca.
                if (in_array(trim((string)$emp->emp_code), $excludeList)) {
                    continue;
                }

                $asistencia = $this->repository->getAsistenciaConHorarioDinamico($emp->id, $startDate, $endDate);

                foreach ($asistencia as $reg) {
                    if (empty($reg->in_time)) continue;

                    $marcajes = $this->procesarMarcajesFisicos($reg);
                    $estatus = $this->determinarEstatus($reg, $marcajes, $reg->fecha);

                    if ($estatus === 'F') {
                        $resultadoFinal[] = [
                            'nomina'   => $emp->emp_code,
                            'nombre'   => "{$emp->first_name} {$emp->last_name}",
                            'departamento' => $emp->dept_name ?? 'N/A',
                            'area'     => $emp->area_name ?? 'Sin Área',
                            'fecha'    => $reg->fecha,
                            'checkin'  => $marcajes['entrada'] ? Carbon::parse($marcajes['entrada'])->format('H:i') : null,
                            'checkout' => $marcajes['salida'] ? Carbon::parse($marcajes['salida'])->format('H:i') : null,
                            'horario'  => ($reg->timetable_name ?? 'Turno') . " (" . substr($reg->in_time, 0, 5) . " a " . substr($reg->off_time, 0, 5) . ")",
                            'observaciones' => $reg->nombre_permiso ?: 'Falta injustificada'
                        ];
                    }
                }
            }
            return $resultadoFinal;

        } catch (Exception $e) {
            Log::error("FaltaService ERROR: " . $e->getMessage());
            throw $e;
        }
    }

    

    private function determinarEstatus($reg, $marcajes, $fechaRegistro)
    {
        // 1. Candado de Fecha: Si el día es HOY o es el FUTURO, no es falta aún.
        $fecha = Carbon::parse($fechaRegistro);
        if ($fecha->isToday() || $fecha->isFuture()) {
            return 'PENDIENTE'; 
        }

        if (!empty($reg->nombre_permiso)) return 'J';
        if ($reg->es_festivo && $reg->enable_holiday) return 'J';
        if (!$marcajes['entrada'] || !$marcajes['salida']) return 'F';
        return 'OK';
    }

    private function procesarMarcajesFisicos($reg)
    {
        $resultados = ['entrada' => null, 'salida' => null];
        if (empty($reg->all_punches)) return $resultados;

        $targetIn = Carbon::parse($reg->fecha . ' ' . $reg->in_time);
        $targetOut = Carbon::parse($reg->fecha . ' ' . $reg->off_time);

        $esVerano = Carbon::parse($reg->fecha)->between(
            Carbon::parse("first sunday of april ".date('Y')),
            Carbon::parse("last sunday of october ".date('Y'))
        );

        $punches = array_filter(explode(',', $reg->all_punches));
        $punchesAjustados = [];

        foreach ($punches as $pStr) {
            $p = Carbon::parse(trim($pStr));
           
            if ($esVerano)  $p->addHour();
            
            $punchesAjustados[] = $p;
        }

        $bestIn = null; $bestOut = null;
        $minDistIn = 999999; $minDistOut = 999999;

        foreach ($punchesAjustados as $h) {
            $distIn = abs($targetIn->diffInMinutes($h, false));
            if ($distIn <= 30) {
                $isPuntual = $h->lte($targetIn);
                $mejorEsPuntual = $bestIn ? $bestIn->lte($targetIn) : false;
                if (!$bestIn || ($isPuntual && !$mejorEsPuntual) || ($isPuntual === $mejorEsPuntual && $distIn < $minDistIn)) {
                    $minDistIn = $distIn; $bestIn = $h;
                }
            }
            $diffOut = $targetOut->diffInMinutes($h, false);
            if ($diffOut >= 0 && $diffOut <= 31) {
                if (!$bestOut || $diffOut < $minDistOut) {
                    $minDistOut = $diffOut; $bestOut = $h;
                }
            }
        }

        $resultados['entrada'] = $bestIn ? $bestIn->format('Y-m-d H:i:s') : null;
        $resultados['salida'] = $bestOut ? $bestOut->format('Y-m-d H:i:s') : null;
        return $resultados;
    }
}