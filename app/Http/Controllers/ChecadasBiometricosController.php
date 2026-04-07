<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ReporteAsistenciasExport;

/**
 * Monitor de Asistencia Cruda - Versión de Producción (Sin Modelos).
 * Incluye ajuste manual de Horario de Verano para vista y exportación.
 * Primeramente Jehová Dios y Jesús Rey.
 */
class ChecadasBiometricosController extends Controller
{
    /**
     * Muestra la vista inicial del monitor.
     */
    public function index()
    {
        return Inertia::render('Checadas_Biometricos/AsistenciaCruda', [
            'checadas' => [],
            'filtros' => null
        ]);
    }

    /**
     * Busca los registros usando Query Builder (DB::table) para mayor compatibilidad con el servidor actual.
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

        $query = $this->construirConsultaBase();

        // Aplicación de filtros de búsqueda
        if ($busqueda) {
            $query->where(function($q) use ($busqueda) {
                $q->where(DB::raw('CAST(e.emp_code AS TEXT)'), 'LIKE', "%{$busqueda}%")
                  ->orWhere('e.first_name', 'ILIKE', "%{$busqueda}%")
                  ->orWhere('e.last_name', 'ILIKE', "%{$busqueda}%");
            });
        }

        // Filtros de fecha
        if ($unica) {
            $query->whereBetween('t.punch_time', [$unica . ' 00:00:00', $unica . ' 23:59:59']);
        } elseif ($inicio && $fin) {
            $query->whereBetween('t.punch_time', [$inicio . ' 00:00:00', $fin . ' 23:59:59']);
        }

        $checadasRaw = $query->orderBy('t.punch_time', 'DESC')->get();

        // Procesamiento de datos (Ajuste de Hora DST y Horarios)
        $checadas = $checadasRaw->map(function($item) {
            return $this->aplicarAjusteHora($item);
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
     * Exporta a Excel aplicando la misma lógica de ajuste de horario de verano.
     */
    public function exportar(Request $request)
    {
        $busqueda = $request->input('codigo_empleado');
        $inicio   = $request->input('fecha_inicio');
        $fin      = $request->input('fecha_fin');
        $unica    = $request->input('fecha_unica');

        $query = $this->construirConsultaBase();

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

        $datosRaw = $query->orderBy('t.punch_time', 'DESC')->get();

        // CLAVE: Aplicamos el ajuste de hora antes de enviar a Excel
        $datosProcesados = $datosRaw->map(function($item) {
            return $this->aplicarAjusteHora($item);
        });

        return Excel::download(
            new ReporteAsistenciasExport($datosProcesados), 
            'Reporte_Checadas_' . date('Ymd_His') . '.xlsx'
        );
    }

    /**
     * Define la estructura de la consulta SQL (JOINs y Subconsultas de horario).
     */
    private function construirConsultaBase()
    {
        return DB::connection('pgsql_biotime')
            ->table('public.iclock_transaction as t')
            ->join('public.personnel_employee as e', 't.emp_id', '=', 'e.id')
            ->leftJoin('public.iclock_terminal as term', 't.terminal_id', '=', 'term.id')
            ->select([
                't.id',
                'e.emp_code as user_id',
                'e.first_name',
                'e.last_name',
                't.punch_time as fecha_hora', // Campo base para ajuste
                'term.alias as dispositivo_nombre',
                't.terminal_sn as sn',
                't.verify_type as tipo_verificacion',
                // Subconsultas para detalles de horario
                DB::raw("(SELECT ti.alias FROM public.att_attschedule sch JOIN public.att_shiftdetail sd ON sch.shift_id = sd.shift_id JOIN public.att_timeinterval ti ON sd.time_interval_id = ti.id WHERE sch.employee_id = e.id AND t.punch_time::date BETWEEN sch.start_date AND sch.end_date AND sd.day_index = extract(dow from t.punch_time)::int LIMIT 1) as horario_nombre"),
                DB::raw("(SELECT ti.in_time FROM public.att_attschedule sch JOIN public.att_shiftdetail sd ON sch.shift_id = sd.shift_id JOIN public.att_timeinterval ti ON sd.time_interval_id = ti.id WHERE sch.employee_id = e.id AND t.punch_time::date BETWEEN sch.start_date AND sch.end_date AND sd.day_index = extract(dow from t.punch_time)::int LIMIT 1) as timetable_in"),
                DB::raw("(SELECT ti.work_time_duration FROM public.att_attschedule sch JOIN public.att_shiftdetail sd ON sch.shift_id = sd.shift_id JOIN public.att_timeinterval ti ON sd.time_interval_id = ti.id WHERE sch.employee_id = e.id AND t.punch_time::date BETWEEN sch.start_date AND sch.end_date AND sd.day_index = extract(dow from t.punch_time)::int LIMIT 1) as timetable_duration"),
                DB::raw("(SELECT ti.allow_late FROM public.att_attschedule sch JOIN public.att_shiftdetail sd ON sch.shift_id = sd.shift_id JOIN public.att_timeinterval ti ON sd.time_interval_id = ti.id WHERE sch.employee_id = e.id AND t.punch_time::date BETWEEN sch.start_date AND sch.end_date AND sd.day_index = extract(dow from t.punch_time)::int LIMIT 1) as timetable_late")
            ]);
    }

    /**
     * Centraliza el ajuste de horario de verano y formateo de objetos.
     */
    private function aplicarAjusteHora($item)
    {
        $p = Carbon::parse($item->fecha_hora);
        
        // Período de ajuste: Primer domingo de abril al último domingo de octubre
        $inicioPrimavera = Carbon::parse("first sunday of april {$p->year}");
        $finOtono = Carbon::parse("last sunday of october {$p->year}");
        
        if ($p->greaterThanOrEqualTo($inicioPrimavera) && $p->lessThan($finOtono)) {
            $p->addHour(1); // Compensación de desfase de base de datos
        }
        
        $item->fecha_hora = $p->format('Y-m-d H:i:s');

        // Estructura de horario para la vista y exportación
        $item->horario = $item->horario_nombre ? [
            'nombre' => $item->horario_nombre,
            'entrada' => $item->timetable_in,
            'duracion' => $item->timetable_duration,
            'tolerancia' => $item->timetable_late,
        ] : null;

        return $item;
    }
}