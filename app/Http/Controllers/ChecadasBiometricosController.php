<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ReporteAsistenciasExport;

class ChecadasBiometricosController extends Controller
{
    /**
     * Muestra la vista inicial del monitor.
     * Gracias a Dios por permitirnos avanzar en la limpieza y funcionalidad del sistema.
     */
    public function index()
    {
        return Inertia::render('Checadas_Biometricos/AsistenciaCruda', [
            'checadas' => [],
            'filtros' => null
        ]);
    }

    /**
     * Busca los registros directamente en la conexión 'pgsql_biotime'.
     * Maneja inteligentemente si se busca por un día único o por rango.
     */
    public function buscar(Request $request)
    {
        $request->validate([
            'codigo_empleado' => 'nullable|string',
            'fecha_inicio'    => 'nullable|date',
            'fecha_fin'       => 'nullable|date',
            'fecha_unica'     => 'nullable|date',
        ]);

        $busqueda = $request->input('codigo_empleado');
        $inicio   = $request->input('fecha_inicio');
        $fin      = $request->input('fecha_fin');
        $unica    = $request->input('fecha_unica');

        $query = DB::connection('pgsql_biotime')
            ->table('public.iclock_transaction as t')
            ->join('public.personnel_employee as e', 't.emp_id', '=', 'e.id')
            ->leftJoin('public.iclock_terminal as term', 't.terminal_id', '=', 'term.id')
            ->select([
                't.id',
                'e.emp_code as user_id',
                'e.first_name',
                'e.last_name',
                't.punch_time as fecha_hora',
                'term.alias as dispositivo_nombre',
                't.terminal_sn as sn',
                't.verify_type as tipo_verificacion',
                // Subconsultas para detalles de horario
                DB::raw("(SELECT ti.alias FROM public.att_attschedule sch JOIN public.att_shiftdetail sd ON sch.shift_id = sd.shift_id JOIN public.att_timeinterval ti ON sd.time_interval_id = ti.id WHERE sch.employee_id = e.id AND t.punch_time::date BETWEEN sch.start_date AND sch.end_date AND sd.day_index = extract(dow from t.punch_time)::int LIMIT 1) as horario_nombre"),
                DB::raw("(SELECT ti.in_time FROM public.att_attschedule sch JOIN public.att_shiftdetail sd ON sch.shift_id = sd.shift_id JOIN public.att_timeinterval ti ON sd.time_interval_id = ti.id WHERE sch.employee_id = e.id AND t.punch_time::date BETWEEN sch.start_date AND sch.end_date AND sd.day_index = extract(dow from t.punch_time)::int LIMIT 1) as timetable_in"),
                DB::raw("(SELECT ti.work_time_duration FROM public.att_attschedule sch JOIN public.att_shiftdetail sd ON sch.shift_id = sd.shift_id JOIN public.att_timeinterval ti ON sd.time_interval_id = ti.id WHERE sch.employee_id = e.id AND t.punch_time::date BETWEEN sch.start_date AND sch.end_date AND sd.day_index = extract(dow from t.punch_time)::int LIMIT 1) as timetable_duration"),
                DB::raw("(SELECT ti.allow_late FROM public.att_attschedule sch JOIN public.att_shiftdetail sd ON sch.shift_id = sd.shift_id JOIN public.att_timeinterval ti ON sd.time_interval_id = ti.id WHERE sch.employee_id = e.id AND t.punch_time::date BETWEEN sch.start_date AND sch.end_date AND sd.day_index = extract(dow from t.punch_time)::int LIMIT 1) as timetable_late")
            ]);

        if ($busqueda) {
            $query->where(function($q) use ($busqueda) {
                $q->where(DB::raw('CAST(e.emp_code AS TEXT)'), 'LIKE', "%{$busqueda}%")
                  ->orWhere('e.first_name', 'ILIKE', "%{$busqueda}%")
                  ->orWhere('e.last_name', 'ILIKE', "%{$busqueda}%");
            });
        }

        if ($unica) {
            $query->whereBetween('t.punch_time', [$unica . ' 00:00:00', $unica . ' 23:59:59']);
        } elseif ($inicio && $fin) {
            $query->whereBetween('t.punch_time', [$inicio . ' 00:00:00', $fin . ' 23:59:59']);
        }

        $checadas = $query->orderBy('t.punch_time', 'DESC')->get();

        $checadas = $checadas->map(function($item) {
            $item->horario = $item->horario_nombre ? [
                'nombre' => $item->horario_nombre,
                'entrada' => $item->timetable_in,
                'duracion' => $item->timetable_duration,
                'tolerancia' => $item->timetable_late,
            ] : null;
            return $item;
        });

        return Inertia::render('Checadas_Biometricos/AsistenciaCruda', [
            'checadas' => $checadas,
            'filtros'  => [
                'codigo_empleado' => $busqueda,
                'fecha_inicio'    => $inicio,
                'fecha_fin'       => $fin,
                'fecha_unica'     => $unica
            ]
        ]);
    }

    /**
     * Exporta a Excel.
     */
    public function exportar(Request $request)
    {
        $busqueda = $request->input('codigo_empleado');
        $inicio = $request->input('fecha_inicio');
        $fin = $request->input('fecha_fin');
        $unica = $request->input('fecha_unica');

        $query = DB::connection('pgsql_biotime')
            ->table('public.iclock_transaction as t')
            ->join('public.personnel_employee as e', 't.emp_id', '=', 'e.id')
            ->leftJoin('public.iclock_terminal as term', 't.terminal_id', '=', 'term.id')
            ->select([
                'e.emp_code as user_id',
                'e.first_name',
                'e.last_name',
                't.punch_time as fecha_hora',
                't.verify_type as tipo_verificacion',
                'term.alias as dispositivo_nombre',
                't.terminal_sn as sn',
                // Datos para resumen de excel
                DB::raw("(SELECT ti.alias FROM public.att_attschedule sch JOIN public.att_shiftdetail sd ON sch.shift_id = sd.shift_id JOIN public.att_timeinterval ti ON sd.time_interval_id = ti.id WHERE sch.employee_id = e.id AND t.punch_time::date BETWEEN sch.start_date AND sch.end_date LIMIT 1) as schedule_name"),
                DB::raw("(SELECT ti.in_time FROM public.att_attschedule sch JOIN public.att_shiftdetail sd ON sch.shift_id = sd.shift_id JOIN public.att_timeinterval ti ON sd.time_interval_id = ti.id WHERE sch.employee_id = e.id AND t.punch_time::date BETWEEN sch.start_date AND sch.end_date LIMIT 1) as schedule_in"),
                DB::raw("(SELECT (ti.in_time::time + (ti.work_time_duration || ' minutes')::interval)::time FROM public.att_attschedule sch JOIN public.att_shiftdetail sd ON sch.shift_id = sd.shift_id JOIN public.att_timeinterval ti ON sd.time_interval_id = ti.id WHERE sch.employee_id = e.id AND t.punch_time::date BETWEEN sch.start_date AND sch.end_date LIMIT 1) as schedule_out")
            ]);

        if ($busqueda) {
            $query->where(function($q) use ($busqueda) {
                $q->where(DB::raw('CAST(e.emp_code AS TEXT)'), 'LIKE', "%{$busqueda}%")
                  ->orWhere('e.first_name', 'ILIKE', "%{$busqueda}%")
                  ->orWhere('e.last_name', 'ILIKE', "%{$busqueda}%");
            });
        }

        if ($unica) {
            $query->whereBetween('t.punch_time', [$unica . ' 00:00:00', $unica . ' 23:59:59']);
        } elseif ($inicio && $fin) {
            $query->whereBetween('t.punch_time', [$inicio . ' 00:00:00', $fin . ' 23:59:59']);
        }

        $datos = $query->orderBy('t.punch_time', 'DESC')->get();
        return Excel::download(new ReporteAsistenciasExport($datos), 'Reporte_Checadas_' . date('Ymd_His') . '.xlsx');
    }
}