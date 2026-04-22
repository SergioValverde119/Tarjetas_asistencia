<?php

namespace App\Repositories\Incidencias;

use Illuminate\Support\Facades\DB;

/**
 * Rasgo para reportes y cálculos de días.
 */
trait ConEstadisticas
{
    public function getEstadisticasGlobales($filtros)
    {
        $paginated = $this->getBaseQuery($filtros)
            ->groupBy('e.id', 'e.first_name', 'e.last_name', 'e.emp_code')
            ->orderBy('total_dias_periodo', 'desc')
            ->paginate(15)->withQueryString();

        $empIds = collect($paginated->items())->pluck('id')->toArray();
        $detalles = $this->getDetallesBulk($empIds, $filtros)->groupBy('employee_id');

        $paginated->getCollection()->transform(function ($emp) use ($detalles) {
            $emp->detalles = $detalles->get($emp->id, collect());
            return $emp;
        });

        return $paginated;
    }

    private function getBaseQuery($filtros)
    {
        $query = DB::connection($this->connection)->table('personnel_employee as e')
            ->join('att_leave as l', 'e.id', '=', 'l.employee_id')
            ->select('e.id', 'e.first_name', 'e.last_name', 'e.emp_code',
                DB::raw("COALESCE(SUM((SELECT count(*) FROM generate_series(l.start_time::date, l.end_time::date, '1 day'::interval) d WHERE extract(dow from d) NOT IN (0, 6) AND NOT EXISTS (SELECT 1 FROM att_holiday h WHERE d::date >= h.start_date AND d::date < (h.start_date + (h.duration_day * interval '1 day'))))), 0) as total_dias_periodo"),
                DB::raw("MIN(l.start_time)::date as primera_incidencia"),
                DB::raw("MAX(l.end_time)::date as ultima_incidencia"));

        if (!empty($filtros['department_id'])) {
            $deptId = (int)$filtros['department_id'];
            $query->whereIn('e.department_id', function($sub) use ($deptId) {
                $sub->select('id')->from(DB::raw("(WITH RECURSIVE sub_depts AS (SELECT id FROM personnel_department WHERE id = $deptId UNION ALL SELECT d.id FROM personnel_department d INNER JOIN sub_depts sd ON d.parent_dept_id = sd.id) SELECT id FROM sub_depts) as jerarquia"));
            });
        }

        if (!($filtros['general'] ?? false) && !empty($filtros['search'])) {
            $term = '%' . strtolower($filtros['search']) . '%';
            $query->where(fn($q) => $q->whereRaw("LOWER(e.first_name || ' ' || e.last_name) LIKE ?", [$term])->orWhereRaw("CAST(e.emp_code AS TEXT) LIKE ?", [$term]));
        }

        return $query;
    }

    public function getDetallesBulk($empleadoIds, $filtros)
    {
        if (empty($empleadoIds)) return collect();
        return DB::connection($this->connection)->table('att_leave as l')->join('att_leavecategory as c', 'l.category_id', '=', 'c.id')
            ->whereIn('l.employee_id', $empleadoIds)
            ->select('l.employee_id', 'c.category_name as tipo', 'c.report_symbol as simbolo', 'l.start_time as desde', 'l.end_time as hasta', 'l.apply_reason as motivo',
                DB::raw("(SELECT count(*) FROM generate_series(l.start_time::date, l.end_time::date, '1 day'::interval) d WHERE extract(dow from d) NOT IN (0, 6)) as dias"))
            ->orderBy('l.start_time', 'desc')->get();
    }

    public function getEstadisticasParaExportar($filtros) {
        return $this->getBaseQuery($filtros)->groupBy('e.id', 'e.first_name', 'e.last_name', 'e.emp_code')->orderBy('total_dias_periodo', 'desc')->get();
    }
}