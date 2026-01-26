<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Exception;

class IncidenciaRepository
{
    // --- CONSULTAS GENERALES ---

    public function getLeaveCategories($search = null)
    {
        $query = DB::connection('pgsql_biotime')
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

    public function getIncidencias($search = null, $fechaRegistro = null, $fechaIncidencia = null)
    {
        $query = DB::connection('pgsql_biotime')
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

        if ($fechaRegistro) $query->whereDate('l.apply_time', $fechaRegistro);
        if ($fechaIncidencia) $query->whereDate('l.start_time', $fechaIncidencia);

        return $query->orderBy('l.apply_time', 'desc')->paginate(15);
    }

    public function getActiveEmployees($search = null)
    {
        $query = DB::connection('pgsql_biotime')
            ->table('personnel_employee')
            ->select('id', 'first_name', 'last_name', 'emp_code', 'department_id')
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

    // --- FUNCIONES DE CREACIÓN (Transacciones) ---

    public function createLeaveCategory($data)
    {
        $name = $data['name'];
        $code = $data['code'];
        $unit = $data['unit'] ?? 3; 

        $exists = DB::connection('pgsql_biotime')->table('att_leavecategory')
            ->where('category_name', $name)
            ->orWhere('report_symbol', $code)
            ->exists();

        if ($exists) {
            throw new Exception("El tipo de permiso '{$name}' o símbolo '{$code}' ya existe.");
        }

        return DB::connection('pgsql_biotime')->table('att_leavecategory')->insertGetId([
            'category_name'       => $name,
            'report_symbol'       => $code, 
            'minimum_unit'        => 1,     
            'unit'                => $unit, 
            'round_off'           => 1,     
            'leave_category_type' => 0
        ]);
    }

    public function createIncidencia($data)
    {
        return DB::connection('pgsql_biotime')->transaction(function () use ($data) {
            
            // 1. INSERTAR EN PADRE (Workflow) - Solo status
            $workflowId = DB::connection('pgsql_biotime')->table('workflow_abstractexception')->insertGetId([
                'audit_status' => 1, 
            ]);

            // 2. INSERTAR EN HIJA (Detalle) - Todos los datos
            DB::connection('pgsql_biotime')->table('att_leave')->insert([
                'abstractexception_ptr_id' => $workflowId, 
                'employee_id'   => $data['employee_id'],
                'category_id'   => $data['category_id'],
                'start_time'    => $data['start_time'],
                'end_time'      => $data['end_time'],
                'apply_reason'  => $data['reason'] ?? 'Sin motivo',
                'apply_time'    => now(),
                'type'          => 1,
                'vacation_number' => 0,
                'audit_time'     => now(),
                'audit_user_id'  => 1,
                'approval_level' => 1 
            ]);

            return $workflowId;
        });
    }

    // --- FUNCIONES DE BÚSQUEDA PARA IMPORTACIÓN (Faltaban estas) ---


    public function findIncidenciaById($id)
    {
        return DB::connection('pgsql_biotime')
            ->table('att_leave')
            ->where('abstractexception_ptr_id', $id)
            ->first();
    }


    public function updateIncidencia($id, $data)
    {
        return DB::connection('pgsql_biotime')
            ->table('att_leave')
            ->where('abstractexception_ptr_id', $id)
            ->update([
                'employee_id' => $data['employee_id'],
                'category_id' => $data['category_id'],
                'start_time'  => $data['start_time'],
                'end_time'    => $data['end_time'],
                'apply_reason'=> $data['reason'],
                // No actualizamos apply_time para conservar la fecha de creación original
            ]);
    }

    /**
     * Buscar ID por CÓDIGO DE NÓMINA
     */
    public function getEmployeeIdByCode($empCode)
    {
        $emp = DB::connection('pgsql_biotime')
            ->table('personnel_employee')
            ->where('emp_code', (string)$empCode)
            ->select('id')
            ->first();
        return $emp ? $emp->id : null;
    }

    /**
     * Buscar ID por NOMBRE COMPLETO
     */
    public function getEmployeeIdByName($fullName)
    {
        // Concatenamos nombre y apellido y buscamos similitud (case insensitive)
        $emp = DB::connection('pgsql_biotime')
            ->table('personnel_employee')
            ->whereRaw("TRIM(first_name || ' ' || last_name) ILIKE ?", [trim($fullName)])
            ->select('id')
            ->first();
        return $emp ? $emp->id : null;
    }

    /**
     * Buscar Categoría por Código
     */
    public function getCategoryIdByCode($code)
    {
        $cat = DB::connection('pgsql_biotime')
            ->table('att_leavecategory')
            ->where('report_symbol', strtoupper($code))
            ->select('id')
            ->first();
        return $cat ? $cat->id : null;
    }
}