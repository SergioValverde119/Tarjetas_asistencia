<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;

/**
 * Repositorio de Horarios Semanales (Estructura Moderna BioTime)
 * Primeramente Jehová Dios y Jesús Rey.
 */
class HorarioRepository
{
    protected $connection = 'pgsql_biotime';

    // ========================================================================
    // MÉTODOS PARA LAS PLANTILLAS DE HORARIOS (CATÁLOGO)
    // ========================================================================

    /**
     * Obtener los Turnos base (Reglas de tiempo)
     */
    public function getTurnosDisponibles()
    {
        return DB::connection($this->connection)
            ->table('att_timeinterval')
            ->select(
                'id', 
                'alias as nombre', 
                'in_time as starttime',
                DB::raw("(in_time::time + (work_time_duration || ' minutes')::interval)::time as endtime"),
                DB::raw("(allow_late - 1) as tolerancia")
            )
            ->orderBy('alias', 'asc')
            ->get();
    }

    /**
     * Obtener el listado de estructuras de horarios
     */
    public function getHorarios($search = null)
    {
        $query = DB::connection($this->connection)
            ->table('att_attshift')
            ->select('id', 'alias as nombre');

        if ($search) {
            $term = '%' . strtolower($search) . '%';
            $query->whereRaw('LOWER(alias) LIKE ?', [$term]);
        }

        return $query->orderBy('alias', 'asc')->get();
    }

    /**
     * MÉTODO RECUPERADO: Obtener los detalles de días para una estructura
     * Este es el método que causaba el error 500.
     */
    public function getDetallesHorario($shiftId)
    {
        return DB::connection($this->connection)
            ->table('att_shiftdetail')
            ->where('shift_id', $shiftId)
            ->select('day_index as dia', 'time_interval_id as turno_id')
            ->get();
    }

    public function createHorario($data)
    {
        return DB::connection($this->connection)->transaction(function () use ($data) {
            $newId = DB::connection($this->connection)
                ->table('att_attshift')
                ->insertGetId(['alias' => $data['nombre']]);

            if (!$newId) throw new Exception("Error al crear la cabecera.");

            $detalles = [];
            foreach ($data['dias'] as $dia => $turno_id) {
                if (!empty($turno_id)) { 
                    $detalles[] = ['shift_id' => $newId, 'time_interval_id' => $turno_id, 'day_index' => $dia];
                }
            }
            if (!empty($detalles)) DB::connection($this->connection)->table('att_shiftdetail')->insert($detalles);
            return $newId;
        });
    }

    public function updateHorario($id, $data)
    {
        return DB::connection($this->connection)->transaction(function () use ($id, $data) {
            DB::connection($this->connection)->table('att_attshift')->where('id', $id)->update(['alias' => $data['nombre']]);
            DB::connection($this->connection)->table('att_shiftdetail')->where('shift_id', $id)->delete();

            $detalles = [];
            foreach ($data['dias'] as $dia => $turno_id) {
                if (!empty($turno_id)) {
                    $detalles[] = ['shift_id' => $id, 'time_interval_id' => $turno_id, 'day_index' => $dia];
                }
            }
            if (!empty($detalles)) DB::connection($this->connection)->table('att_shiftdetail')->insert($detalles);
            return $id;
        });
    }

    public function deleteHorario($id)
    {
        return DB::connection($this->connection)->transaction(function () use ($id) {
            DB::connection($this->connection)->table('att_shiftdetail')->where('shift_id', $id)->delete();
            return DB::connection($this->connection)->table('att_attshift')->where('id', $id)->delete();
        });
    }

    // ========================================================================
    // MÉTODOS PARA BÚSQUEDA INDIVIDUAL (EMPLEADOS)
    // ========================================================================

    public function getHorarioPorNomina($search)
    {
        $term = '%' . strtolower(trim($search)) . '%';
        $empleados = DB::connection($this->connection)
            ->table('personnel_employee as e')
            ->leftJoin('personnel_department as d', 'e.department_id', '=', 'd.id')
            ->where(function($q) use ($search, $term) {
                $q->whereRaw('CAST(e.emp_code AS TEXT) = ?', [trim($search)])
                  ->orWhereRaw("LOWER(COALESCE(e.first_name, '')) LIKE ?", [$term])
                  ->orWhereRaw("LOWER(COALESCE(e.last_name, '')) LIKE ?", [$term])
                  ->orWhereRaw("LOWER(COALESCE(e.first_name, '') || ' ' || COALESCE(e.last_name, '')) LIKE ?", [$term]);
            })
            ->select('e.id', 'e.emp_code', 'e.first_name', 'e.last_name', 'd.dept_name as department_name')
            ->get();

        if ($empleados->isEmpty()) return [];

        $resultadosFinales = [];
        foreach ($empleados as $empleado) {
            $resultado = ['empleado' => $empleado, 'horarioSemanal' => ['nombre_turno' => 'Sin Horario Fijo', 'dias' => []]];
            for ($i = 0; $i <= 6; $i++) { $resultado['horarioSemanal']['dias'][$i] = ['dia_index' => $i, 'activo' => false]; }

            $asignacion = DB::connection($this->connection)
                ->table('att_attschedule as asch')
                ->join('att_attshift as s', 'asch.shift_id', '=', 's.id')
                ->where('asch.employee_id', $empleado->id)
                ->whereRaw('CURRENT_DATE BETWEEN asch.start_date AND asch.end_date')
                ->select('s.id', 's.alias as nombre_turno')
                ->first();

            if (!$asignacion) {
                $asignacion = DB::connection($this->connection)
                    ->table('personnel_employee as e')
                    ->join('att_departmentschedule as dsch', 'e.department_id', '=', 'dsch.department_id')
                    ->join('att_attshift as s', 'dsch.shift_id', '=', 's.id')
                    ->where('e.id', $empleado->id)
                    ->select('s.id', 's.alias as nombre_turno')
                    ->first();
            }

            if ($asignacion) {
                $resultado['horarioSemanal']['nombre_turno'] = $asignacion->nombre_turno;
                $detalles = DB::connection($this->connection)
                    ->table('att_shiftdetail as sd')
                    ->join('att_timeinterval as ti', 'sd.time_interval_id', '=', 'ti.id')
                    ->where('sd.shift_id', $asignacion->id)
                    ->select('sd.day_index', 'ti.in_time', DB::raw("(ti.in_time::time + (COALESCE(ti.work_time_duration, 0) || ' minutes')::interval)::time as out_time"))
                    ->get();

                foreach ($detalles as $det) {
                    $resultado['horarioSemanal']['dias'][$det->day_index] = [
                        'dia_index' => $det->day_index, 'activo' => true,
                        'in_time' => substr($det->in_time, 0, 5), 'out_time' => substr($det->out_time, 0, 5)
                    ];
                }
            }
            $resultado['horarioSemanal']['dias'] = array_values($resultado['horarioSemanal']['dias']);
            $resultadosFinales[] = $resultado;
        }
        return $resultadosFinales;
    }

    public function getHistorialHorarios($nomina)
    {
        $empleado = DB::connection($this->connection)
            ->table('personnel_employee as e')
            ->leftJoin('personnel_department as d', 'e.department_id', '=', 'd.id')
            ->whereRaw('CAST(e.emp_code AS TEXT) = ?', [(string)$nomina])
            ->select('e.id', 'e.emp_code', 'e.first_name', 'e.last_name', 'd.dept_name as department_name')
            ->first();

        if (!$empleado) return null;

        $historialRaw = DB::connection($this->connection)
            ->table('att_attschedule as sch')
            ->join('att_attshift as s', 'sch.shift_id', '=', 's.id')
            ->where('sch.employee_id', $empleado->id)
            ->select('sch.id', 'sch.shift_id', 's.alias as nombre_turno', 'sch.start_date', 'sch.end_date')
            ->orderBy('sch.start_date', 'desc')
            ->get();

        $historialDetallado = $historialRaw->map(function($item) {
            $detalles = DB::connection($this->connection)
                ->table('att_shiftdetail as sd')
                ->join('att_timeinterval as ti', 'sd.time_interval_id', '=', 'ti.id')
                ->where('sd.shift_id', $item->shift_id)
                ->select(
                    'sd.day_index', 
                    'ti.in_time', 
                    DB::raw("(ti.in_time::time + (COALESCE(ti.work_time_duration, 0) || ' minutes')::interval)::time as out_time"),
                    DB::raw("(ti.allow_late - 1) as tolerancia")
                )
                ->get();

            $dias = [];
            for ($i = 0; $i <= 6; $i++) { $dias[$i] = ['activo' => false]; }

            $toleranciaMax = 0;
            foreach ($detalles as $det) {
                $dias[$det->day_index] = [
                    'activo' => true,
                    'in' => substr($det->in_time, 0, 5),
                    'out' => substr($det->out_time, 0, 5)
                ];
                if ($det->tolerancia > $toleranciaMax) $toleranciaMax = $det->tolerancia;
            }

            $item->dias = $dias;
            $item->tolerancia = $toleranciaMax;
            return $item;
        });

        return ['empleado' => $empleado, 'historial' => $historialDetallado];
    }

    public function asignarHorarioEmpleado($nomina, $shiftId, $startDate, $endDate)
    {
        return DB::connection($this->connection)->transaction(function () use ($nomina, $shiftId, $startDate, $endDate) {
            $empleado = DB::connection($this->connection)->table('personnel_employee')->whereRaw('CAST(emp_code AS TEXT) = ?', [(string)$nomina])->first();
            if (!$empleado) throw new Exception("Empleado no encontrado.");
            $empId = $empleado->id;

            $overlaps = DB::connection($this->connection)->table('att_attschedule')->where('employee_id', $empId)->where('start_date', '<=', $endDate)->where('end_date', '>=', $startDate)->get();

            foreach ($overlaps as $old) {
                $start1 = Carbon::parse($old->start_date)->startOfDay();
                $end1   = Carbon::parse($old->end_date)->startOfDay();
                $start2 = Carbon::parse($startDate)->startOfDay();
                $end2   = Carbon::parse($endDate)->startOfDay();

                if ($start1->lt($start2) && $end1->gt($end2)) {
                    DB::connection($this->connection)->table('att_attschedule')->where('id', $old->id)->update(['end_date' => $start2->copy()->subDay()->format('Y-m-d')]);
                    DB::connection($this->connection)->table('att_attschedule')->insert(['employee_id' => $empId, 'shift_id' => $old->shift_id, 'start_date' => $end2->copy()->addDay()->format('Y-m-d'), 'end_date' => $old->end_date]);
                } elseif ($start1->lt($start2)) {
                    DB::connection($this->connection)->table('att_attschedule')->where('id', $old->id)->update(['end_date' => $start2->copy()->subDay()->format('Y-m-d')]);
                } elseif ($end1->gt($end2)) {
                    DB::connection($this->connection)->table('att_attschedule')->where('id', $old->id)->update(['start_date' => $end2->copy()->addDay()->format('Y-m-d')]);
                } else {
                    DB::connection($this->connection)->table('att_attschedule')->where('id', $old->id)->delete();
                }
            }
            DB::connection($this->connection)->table('att_attschedule')->insert(['employee_id' => $empId, 'shift_id' => $shiftId, 'start_date' => $startDate, 'end_date' => $endDate]);
        });
    }

    public function deleteAsignacion($id)
    {
        return DB::connection($this->connection)->transaction(function () use ($id) {
            DB::connection($this->connection)->table('att_payloadexception')->where('item_id', (string)$id)->delete();
            return DB::connection($this->connection)->table('att_attschedule')->where('id', $id)->delete();
        });
    }
}