<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;

/**
 * Repositorio de Incidencias (Inyección Directa SQL)
 * Versión Blindada contra "Unique Violation" e ID Huérfanos.
 * Primeramente Jehová Dios y Jesús Rey.
 */
class IncidenciaRepository
{
    // CONEXIÓN MAESTRA: Base de datos BioTime Original
    protected $connection = 'pgsql_original';

    /**
     * CREACIÓN DIRECTA EN BD (Sin API)
     * Realiza una inserción atómica en las tablas de BioTime.
     */
    public function createIncidencia($data)
    {
        return DB::connection($this->connection)->transaction(function () use ($data) {
            
            // BLINDAJE 1: Zona Horaria
            DB::connection($this->connection)->statement("SET TIME ZONE 'Etc/GMT+6'");

            // 1. Insertar en la TABLA PADRE (workflow_abstractexception)
            // Laravel obtendrá el ID de la secuencia automáticamente
            $newId = DB::connection($this->connection)
                ->table('workflow_abstractexception')
                ->insertGetId([
                    'audit_status' => 1, // 1 = Aprobado
                ]);

            if (!$newId) {
                throw new Exception("No se pudo generar el folio en workflow_abstractexception.");
            }

            // BLINDAJE 2: ANTI-HUÉRFANOS
            // Si por algún error previo existe el hijo pero no el padre, o viceversa,
            // aseguramos que el espacio para $newId en att_leave esté limpio.
            DB::connection($this->connection)
                ->table('att_leave')
                ->where('abstractexception_ptr_id', $newId)
                ->delete();

            $ahoraMexico = Carbon::now('Etc/GMT+6')->format('Y-m-d H:i:s');

            // 2. Insertar en la TABLA HIJA (att_leave)
            DB::connection($this->connection)
                ->table('att_leave')
                ->insert([
                    'abstractexception_ptr_id' => $newId,
                    'employee_id'   => $data['employee_id'],
                    'category_id'   => $data['category_id'],
                    'start_time'    => Carbon::parse($data['start_time'])->format('Y-m-d H:i:s'),
                    'end_time'      => Carbon::parse($data['end_time'])->format('Y-m-d H:i:s'),
                    'apply_reason'  => $data['reason'] ?? 'Captura Directa Sistema Asistencia',
                    'apply_time'    => $ahoraMexico,
                    'audit_time'    => $ahoraMexico,
                    'audit_user_id' => 1,
                    'type'          => 1,
                    'vacation_number' => 0
                ]);

            // 3. Limpiar caché de BioTime (att_payloadexception)
            DB::connection($this->connection)
                ->table('att_payloadexception')
                ->where('item_id', (string)$newId)
                ->delete();

            return $newId;
        });
    }

    /**
     * BORRADO DIRECTO EN BD (Sin API)
     */
    public function deleteIncidencia($id)
    {
        return DB::connection($this->connection)->transaction(function () use ($id) {
            DB::connection($this->connection)->table('att_payloadexception')->where('item_id', (string)$id)->delete();
            DB::connection($this->connection)->table('att_leave')->where('abstractexception_ptr_id', $id)->delete();
            return DB::connection($this->connection)->table('workflow_abstractexception')->where('id', $id)->delete();
        });
    }

    public function getLeaveCategories($search = null)
    {
        $query = DB::connection($this->connection)
            ->table('att_leavecategory')
            ->select('id', 'category_name as name', 'report_symbol as code', 'unit');

        if ($search) {
            $term = '%' . strtolower($search) . '%';
            $query->where(function($q) use ($term) {
                $q->whereRaw('LOWER(category_name) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(report_symbol) LIKE ?', [$term]);
            });
        }
        return $query->orderBy('id', 'asc')->get();
    }

    public function getIncidencias($search = null, $fechaRegistro = null, $fechaIncidencia = null, $dateStart = null, $dateEnd = null)
    {
        $query = DB::connection($this->connection)
            ->table('att_leave as l')
            ->join('personnel_employee as e', 'l.employee_id', '=', 'e.id')
            ->join('att_leavecategory as c', 'l.category_id', '=', 'c.id')
            ->select(
                'l.abstractexception_ptr_id as id', 
                'e.first_name', 
                'e.last_name', 
                'e.emp_code',
                'c.category_name as tipo',
                'l.start_time', 
                'l.end_time', 
                'l.apply_reason', 
                'l.apply_time'
            );

        if ($search) {
            $term = '%' . strtolower($search) . '%';
            $query->where(function($q) use ($term) {
                $q->whereRaw('LOWER(e.first_name) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(e.last_name) LIKE ?', [$term])
                  ->orWhere('e.emp_code', 'LIKE', $term);
            });
        }

        if ($fechaRegistro) {
            $query->whereDate('l.apply_time', $fechaRegistro);
        }

        if ($fechaIncidencia) {
        // Buscamos incidencias que estén vigentes durante cualquier momento de ese día
        $query->where('l.start_time', '<=', $fechaIncidencia . ' 23:59:59')
              ->where('l.end_time', '>=', $fechaIncidencia . ' 00:00:00');
        }

        if ($dateStart && $dateEnd) {
            $query->where('l.start_time', '<=', $dateEnd . ' 23:59:59')
                  ->where('l.end_time', '>=', $dateStart . ' 00:00:00');
        }

        return $query->orderBy('l.apply_time', 'desc')->paginate(15);
    }

    public function getActiveEmployees($search = null)
    {
        $query = DB::connection($this->connection)
            ->table('personnel_employee')
            ->select('id', 'first_name', 'last_name', 'emp_code')
            ->where('status', 0); 

        if ($search) {
            $term = '%' . strtolower($search) . '%';
            $query->where(function($q) use ($term) {
                $q->whereRaw('LOWER(first_name) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(last_name) LIKE ?', [$term])
                  ->orWhere('emp_code', 'LIKE', $term);
            });
        } else {
            $query->limit(50); 
        }

        return $query->orderBy('first_name', 'asc')->get();
    }

    public function findOverlap($employeeId, $start, $end, $ignoreId = null)
    {
        $startDay = Carbon::parse($start)->startOfDay()->format('Y-m-d H:i:s');
        $endDay = Carbon::parse($end)->endOfDay()->format('Y-m-d H:i:s');

        $query = DB::connection($this->connection)
            ->table('att_leave')
            ->where('employee_id', $employeeId)
            ->where(function ($q) use ($startDay, $endDay) {
                $q->where('start_time', '<=', $endDay)
                  ->where('end_time', '>=', $startDay);
            });

        if ($ignoreId) {
            $query->where('abstractexception_ptr_id', '!=', $ignoreId);
        }

        return $query->first();
    }

    public function findIncidenciaById($id)
    {
        return DB::connection($this->connection)
            ->table('att_leave as l')
            ->join('personnel_employee as e', 'l.employee_id', '=', 'e.id')
            ->select(
                'l.*', 
                'l.abstractexception_ptr_id as id', 
                'e.first_name', 
                'e.last_name', 
                'e.emp_code'
            )
            ->where('l.abstractexception_ptr_id', $id)
            ->first();
    }

    public function updateIncidencia($id, $data)
    {
        return DB::connection($this->connection)->transaction(function () use ($id, $data) {
            DB::connection($this->connection)->statement("SET TIME ZONE 'Etc/GMT+6'");
            $ahoraMexico = Carbon::now('Etc/GMT+6')->format('Y-m-d H:i:s');
            DB::connection($this->connection)->table('att_payloadexception')->where('item_id', (string)$id)->delete();
            DB::connection($this->connection)->table('workflow_abstractexception')->where('id', $id)->update(['audit_status' => 1]);

            return DB::connection($this->connection)
                ->table('att_leave')
                ->where('abstractexception_ptr_id', $id)
                ->update([
                    'employee_id'   => $data['employee_id'],
                    'category_id'   => $data['category_id'],
                    'start_time'    => Carbon::parse($data['start_time'])->format('Y-m-d H:i:s'),
                    'end_time'      => Carbon::parse($data['end_time'])->format('Y-m-d H:i:s'),
                    'apply_reason'  => $data['reason'],
                    'audit_time'    => $ahoraMexico,
                    'audit_user_id' => 1
                ]);
        });
    }

    public function getEmployeeIdByCode($empCode)
    {
        $emp = DB::connection($this->connection)->table('personnel_employee')->where('emp_code', (string)$empCode)->select('id')->first();
        return $emp ? $emp->id : null;
    }

    public function getEmployeeIdByName($fullName)
    {
        $emp = DB::connection($this->connection)->table('personnel_employee')->whereRaw("TRIM(first_name || ' ' || last_name) ILIKE ?", [trim($fullName)])->select('id')->first();
        return $emp ? $emp->id : null;
    }

    public function getCategoryIdByCode($code)
    {
        $cat = DB::connection($this->connection)->table('att_leavecategory')->where('report_symbol', strtoupper($code))->select('id')->first();
        return $cat ? $cat->id : null;
    }

    public function createLeaveCategory($data)
    {
        $name = $data['name'];
        $code = $data['code'];
        $unit = $data['unit'] ?? 3; 
        $exists = DB::connection($this->connection)->table('att_leavecategory')->where('category_name', $name)->orWhere('report_symbol', $code)->exists();
        if ($exists) { throw new Exception("El tipo de permiso '{$name}' o símbolo '{$code}' ya existe."); }
        return DB::connection($this->connection)->table('att_leavecategory')->insertGetId([
            'category_name'       => $name,
            'report_symbol'       => $code, 
            'minimum_unit'        => 1,     
            'unit'                => $unit, 
            'round_off'           => 1,     
            'leave_category_type' => 0
        ]);
    }


    public function getEstadisticasGlobales($filtros)
    {
        $query = DB::connection($this->connection)
            ->table('personnel_employee as e')
            ->join('att_leave as l', 'e.id', '=', 'l.employee_id')
            ->select(
                'e.id', 
                'e.first_name', 
                'e.last_name', 
                'e.emp_code',
                DB::raw("COALESCE(SUM((l.end_time::date - l.start_time::date) + 1), 0) as total_dias_periodo"),
                DB::raw("MIN(l.start_time)::date as primera_incidencia"),
                DB::raw("MAX(l.end_time)::date as ultima_incidencia")
            );

        // LÓGICA DE FILTRADO EXCLUYENTE
        if (!empty($filtros['date_start']) && !empty($filtros['date_end'])) {
            $query->where('l.start_time', '>=', $filtros['date_start'] . ' 00:00:00')
                  ->where('l.start_time', '<=', $filtros['date_end'] . ' 23:59:59');
        } else if (!empty($filtros['ano'])) {
            $query->whereYear('l.start_time', $filtros['ano']);
        }

        if (!($filtros['general'] ?? false) && !empty($filtros['search'])) {
            $term = '%' . strtolower($filtros['search']) . '%';
            $query->where(function($q) use ($term) {
                $q->whereRaw("LOWER(e.first_name || ' ' || e.last_name) LIKE ?", [$term])
                  ->orWhereRaw("CAST(e.emp_code AS TEXT) LIKE ?", [$term]);
            });
        }

        return $query->groupBy('e.id', 'e.first_name', 'e.last_name', 'e.emp_code')
            ->orderBy('total_dias_periodo', 'desc')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * Obtiene el detalle para los niveles 2 y 3.
     */
    public function getDetallePorEmpleado($empleadoId, $filtros)
    {
        $query = DB::connection($this->connection)
            ->table('att_leave as l')
            ->join('att_leavecategory as c', 'l.category_id', '=', 'c.id')
            ->where('l.employee_id', $empleadoId);

        if (!empty($filtros['date_start']) && !empty($filtros['date_end'])) {
            $query->where('l.start_time', '>=', $filtros['date_start'] . ' 00:00:00')
                  ->where('l.start_time', '<=', $filtros['date_end'] . ' 23:59:59');
        } else if (!empty($filtros['ano'])) {
            $query->whereYear('l.start_time', $filtros['ano']);
        }

        return $query->select(
                'c.category_name as tipo',
                'c.report_symbol as simbolo',
                'l.start_time as desde',
                'l.end_time as hasta',
                'l.apply_reason as motivo',
                DB::raw("(l.end_time::date - l.start_time::date) + 1 as dias")
            )
            ->orderBy('l.start_time', 'desc')
            ->get();
    }
}