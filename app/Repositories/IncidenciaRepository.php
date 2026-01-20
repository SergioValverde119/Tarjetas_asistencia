<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Exception;

class IncidenciaRepository
{
    /**
     * Obtiene el catálogo de Tipos de Permiso.
     * Estructura verificada: usa 'report_symbol' y 'category_name'.
     */
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

    /**
     * Obtiene listado de incidencias para la vista principal.
     */
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

        if ($fechaRegistro) {
            $query->whereDate('l.apply_time', $fechaRegistro);
        }

        if ($fechaIncidencia) {
            $query->whereDate('l.start_time', $fechaIncidencia);
        }

        return $query->orderBy('l.apply_time', 'desc') 
            ->paginate(15);
    }

    /**
     * Crear Categoría (Estructura Verificada)
     * Solo insertamos las columnas que confirmaste existen:
     * id, category_name, minimum_unit, unit, round_off, report_symbol, leave_category_type
     */
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
            // Eliminados: company_id, is_report (No existen en tu versión)
        ]);
    }

    /**
     * Obtiene empleados activos.
     */
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

    /**
     * REGISTRAR INCIDENCIA (Estructura Verificada)
     * Ajustado a tus columnas reales.
     */
    public function createIncidencia($data)
    {
        return DB::connection('pgsql_biotime')->transaction(function () use ($data) {
            
            // 1. INSERTAR EN PADRE (Workflow)
            // Tu tabla SOLO tiene id, audit_status y revoke_reason.
            $workflowId = DB::connection('pgsql_biotime')->table('workflow_abstractexception')->insertGetId([
                'audit_status' => 1, // 1 = Aceptado
            ]);

            // 2. INSERTAR EN HIJA (Detalle)
            // Aquí van todos los detalles de auditoría y fechas.
            DB::connection('pgsql_biotime')->table('att_leave')->insert([
                'abstractexception_ptr_id' => $workflowId, 
                'employee_id'   => $data['employee_id'],
                'category_id'   => $data['category_id'],
                'start_time'    => $data['start_time'],
                'end_time'      => $data['end_time'],
                'apply_reason'  => $data['reason'] ?? 'Carga Web Laravel',
                'apply_time'    => now(),
                'type'          => 1,
                'vacation_number' => 0,
                
                // Campos de auditoría (Existen en att_leave según tu select)
                'audit_time'     => now(),
                'audit_reason'   => 'Aprobado Automático Web',
                'audit_user_id'  => 1, // Admin por defecto
                'approver'       => 'Admin Web', // Campo texto para mostrar nombre
                'approval_level' => 1 
            ]);

            return $workflowId;
        });
    }
}