<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use App\Services\BiotimeApiService;
use Exception;
use Carbon\Carbon;

/**
 * Repositorio de Incidencias
 * Primeramente Jehová Dios y Jesús Rey.
 */
class IncidenciaRepository
{
    protected $apiService;

    // CONEXIÓN MAESTRA: Usamos la BD Original para evitar problemas de latencia de replicación
    protected $connection = 'pgsql_original';

    public function __construct(BiotimeApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    /**
     * CREACIÓN HÍBRIDA: Crea vía API y aprueba vía SQL Directo.
     * Ajustado para omitir la columna 'status' en workflow_abstractexception.
     */
    public function createIncidencia($data)
    {
        // 0. Formateo de fechas para BioTime
        $fechaInicio = Carbon::parse($data['start_time'])->format('Y-m-d H:i:s');
        $fechaFin = Carbon::parse($data['end_time'])->format('Y-m-d H:i:s');

        // 1. Enviamos la orden a la API de BioTime para crear el registro base (nace como pendiente)
        $apiResponse = $this->apiService->crearPermiso([
            'employee_id'   => $data['employee_id'],
            'leave_type_id' => $data['category_id'],
            'fecha_inicio'  => $fechaInicio,
            'fecha_fin'     => $fechaFin,
            'reason'        => $data['reason'] ?? 'Sin motivo'
        ]);

        if (!$apiResponse['success']) {
            throw new Exception("Error en API BioTime al crear incidencia: " . json_encode($apiResponse['error']));
        }

        // 2. Obtenemos el ID que generó la API
        $newId = $apiResponse['data']['id'] ?? null;

        if ($newId) {
            // 3. APROBACIÓN QUIRÚRGICA EN BD ORIGINAL
            
            // A. En la tabla PADRE solo actualizamos 'audit_status'
            // (Se eliminó 'status' por no existir en tu versión de BioTime)
            DB::connection($this->connection)
                ->table('workflow_abstractexception')
                ->where('id', $newId)
                ->update([
                    'audit_status' => 1, 
                ]);

            // B. En la tabla HIJA (att_leave) registramos los tiempos de aprobación
            DB::connection($this->connection)
                ->table('att_leave')
                ->where('abstractexception_ptr_id', $newId)
                ->update([
                    'audit_time'    => now(),
                    'audit_user_id' => 1, 
                    'audit_reason'  => 'Aprobado automáticamente por Sistema Externo'
                ]);
        } else {
            throw new Exception("La API de BioTime creó el registro pero no devolvió el ID necesario para la aprobación.");
        }

        return $newId;
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
            // Limpiar caché de cálculos de BioTime para forzar el recálculo con los nuevos datos
            DB::connection($this->connection)
                ->table('att_payloadexception')
                ->where('item_id', (string)$id)
                ->delete();

            // Al editar, nos aseguramos de que el audit_status siga siendo 1 (Aprobado)
            DB::connection($this->connection)
                ->table('workflow_abstractexception')
                ->where('id', $id)
                ->update(['audit_status' => 1]);

            return DB::connection($this->connection)
                ->table('att_leave')
                ->where('abstractexception_ptr_id', $id)
                ->update([
                    'employee_id'   => $data['employee_id'],
                    'category_id'   => $data['category_id'],
                    'start_time'    => $data['start_time'],
                    'end_time'      => $data['end_time'],
                    'apply_reason'  => $data['reason'],
                    'audit_time'    => now(),
                    'audit_user_id' => 1
                ]);
        });
    }

    public function deleteIncidencia($id)
    {
        return $this->apiService->borrarPermiso($id);
    }

    public function getEmployeeIdByCode($empCode)
    {
        $emp = DB::connection($this->connection)
            ->table('personnel_employee')
            ->where('emp_code', (string)$empCode)
            ->select('id')
            ->first();
        return $emp ? $emp->id : null;
    }

    public function getEmployeeIdByName($fullName)
    {
        $emp = DB::connection($this->connection)
            ->table('personnel_employee')
            ->whereRaw("TRIM(first_name || ' ' || last_name) ILIKE ?", [trim($fullName)])
            ->select('id')
            ->first();
        return $emp ? $emp->id : null;
    }

    public function getCategoryIdByCode($code)
    {
        $cat = DB::connection($this->connection)
            ->table('att_leavecategory')
            ->where('report_symbol', strtoupper($code))
            ->select('id')
            ->first();
        return $cat ? $cat->id : null;
    }

    public function createLeaveCategory($data)
    {
        $name = $data['name'];
        $code = $data['code'];
        $unit = $data['unit'] ?? 3; 

        $exists = DB::connection($this->connection)->table('att_leavecategory')
            ->where('category_name', $name)
            ->orWhere('report_symbol', $code)
            ->exists();

        if ($exists) {
            throw new Exception("El tipo de permiso '{$name}' o símbolo '{$code}' ya existe.");
        }

        return DB::connection($this->connection)->table('att_leavecategory')->insertGetId([
            'category_name'       => $name,
            'report_symbol'       => $code, 
            'minimum_unit'        => 1,     
            'unit'                => $unit, 
            'round_off'           => 1,     
            'leave_category_type' => 0
        ]);
    }
}