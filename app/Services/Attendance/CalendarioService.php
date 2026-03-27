<?php

namespace App\Services\Attendance;

use Carbon\Carbon;


class CalendarioService
{
    /**
     * Resuelve el turno técnico para una fecha específica.
     */
    public function obtenerHorarioParaFecha($empleado, $fechaStr)
    {
        $fecha = Carbon::parse($fechaStr)->toDateString();
        $diaDeLaSemana = Carbon::parse($fechaStr)->dayOfWeek; // 0 (Dom) a 6 (Sáb)

        // 1. Prioridad Individual: Horarios asignados directamente
        $asignacion = $empleado->schedules->first(function ($s) use ($fecha) {
            return $fecha >= $s->start_date->toDateString() && $fecha <= $s->end_date->toDateString();
        });

        $turno = $asignacion ? $asignacion->shift : ($empleado->department?->schedules?->first()?->shift ?? null);

        if (!$turno) return null;

        // 2. Extraer el intervalo de tiempo para ese día de la semana
        $detalle = $turno->detalles->where('day_index', $diaDeLaSemana)->first();
        $intervalo = $detalle?->interval;

        if (!$intervalo) return null;

        return (object)[
            'nombre_turno' => $intervalo->alias ?? $turno->alias,
            'entrada_oficial' => $intervalo->in_time,
            'duracion_minutos' => (int)($intervalo->work_time_duration ?? 480),
            'tolerancia' => (int)($intervalo->allow_late ?? 0),
        ];
    }
}