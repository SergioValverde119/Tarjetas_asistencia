<?php

namespace App\Services;

use App\Repositories\FaltaRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Servicio para el procesamiento lógico de Faltas.
 * Clasifica ausencias basado en el cruce de huellas vs horario oficial.
 * Primeramente Jehová Dios y Jesús Rey.
 */
class FaltaService
{
    protected $repository;

    public function __construct(FaltaRepository $repository)
    {
        $this->repository = $repository;
    }

    public function procesarReporteFaltas($areaId, $empId, $startDate, $endDate, $exclude = [])
    {
        try {
            $empleados = $this->repository->getEmpleadosParaMonitoreo($areaId, $empId, $exclude);
            $resultadoFinal = [];

            foreach ($empleados as $emp) {
                $asistencia = $this->repository->getAsistenciaConHorarioDinamico($emp->id, $startDate, $endDate);

                if (empty($asistencia)) {
                    continue;
                }

                foreach ($asistencia as $reg) {
                    // Si el día no tiene in_time, BioTime no lo cuenta como laborable
                    if (empty($reg->in_time)) {
                        continue;
                    }

                    $marcajes = $this->procesarMarcajesFisicos($reg);
                    $estatus = $this->determinarEstatus($reg, $marcajes);

                    if ($estatus === 'F') {
                        $resultadoFinal[] = [
                            'nomina'   => $emp->emp_code,
                            'nombre'   => "{$emp->first_name} {$emp->last_name}",
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
            Log::error("FaltaService@procesarReporteFaltas FATAL ERROR: " . $e->getMessage());
            throw $e;
        }
    }

    private function determinarEstatus($reg, $marcajes)
    {
        if (!empty($reg->nombre_permiso)) return 'J';
        if ($reg->es_festivo && $reg->enable_holiday) return 'J';
        
        // Si falta la entrada O la salida válida, es falta
        if (!$marcajes['entrada'] || !$marcajes['salida']) {
            return 'F';
        }

        return 'OK';
    }

    private function procesarMarcajesFisicos($reg)
    {
        $resultados = ['entrada' => null, 'salida' => null];
        if (empty($reg->all_punches)) {
            return $resultados;
        }

        $fecha = Carbon::parse($reg->fecha);
        $targetIn = Carbon::parse($reg->fecha . ' ' . $reg->in_time);
        $targetOut = Carbon::parse($reg->fecha . ' ' . $reg->off_time);

        $year = $fecha->year;
        $esVerano = $fecha->between(
            Carbon::parse("first sunday of april $year"),
            Carbon::parse("last sunday of october $year")
        );

        $punchesRaw = array_filter(explode(',', $reg->all_punches));
        $punches = [];

        foreach ($punchesRaw as $pStr) {
            $p = Carbon::parse(trim($pStr));
            if ($esVerano) $p->addHour();
            $punches[] = $p;
        }

        $bestIn = null; $bestOut = null;
        $minDistIn = 999999; $minDistOut = 999999;

        foreach ($punches as $h) {
            $distIn = abs($targetIn->diffInMinutes($h, false));
            if ($distIn <= 30) {
                $esPuntual = $h->lte($targetIn);
                $mejorEsPuntual = $bestIn ? $bestIn->lte($targetIn) : false;
                if (!$bestIn || ($esPuntual && !$mejorEsPuntual) || ($esPuntual === $mejorEsPuntual && $distIn < $minDistIn)) {
                    $minDistIn = $distIn;
                    $bestIn = $h;
                }
            }

            $diffOut = $targetOut->diffInMinutes($h, false);
            if ($diffOut >= 0 && $diffOut <= 31) {
                if (!$bestOut || $diffOut < $minDistOut) {
                    $minDistOut = $diffOut;
                    $bestOut = $h;
                }
            }
        }

        $resultados['entrada'] = $bestIn ? $bestIn->format('Y-m-d H:i:s') : null;
        $resultados['salida'] = $bestOut ? $bestOut->format('Y-m-d H:i:s') : null;

        return $resultados;
    }
}