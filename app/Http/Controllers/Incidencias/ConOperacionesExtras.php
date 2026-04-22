<?php

namespace App\Http\Controllers\Incidencias;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\LogModificacionIncidencia;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\IncidenciasEstadisticasExport;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Rasgo (Trait) para funciones de Inyección Masiva y Reportes.
 * Primeramente Jehová Dios y Jesús Rey.
 */
trait ConOperacionesExtras
{
    /**
     * Vista de Inyección por Sección Sindical (Áreas).
     */
    public function createBySection()
    {
        return Inertia::render('Incidencias/CrearPorSeccion', [
            'areas'      => $this->repository->getAreas(),
            'categories' => $this->repository->getLeaveCategories(),
        ]);
    }

    /**
     * Procesa la inyección masiva por área.
     */
    public function storeBySection(Request $request)
    {
        $validated = $request->validate([
            'area_id'     => 'required|integer', 
            'category_id' => 'required|integer',
            'start_time'  => 'required|date',
            'end_time'    => 'required|date|after_or_equal:start_time',
            'reason'      => 'required|string|max:250',
        ]);

        try {
            $employeeIds = DB::connection('pgsql_biotime')
                ->table('personnel_employee as e')
                ->join('personnel_employee_area as ea', 'e.id', '=', 'ea.employee_id')
                ->where('ea.area_id', $validated['area_id'])
                ->where('e.status', 0)
                ->pluck('e.id');

            if ($employeeIds->isEmpty()) {
                return redirect()->back()->with('error', "No se encontraron empleados activos en esta área.");
            }

            $ingresados = 0; $omitidos = 0;
            DB::transaction(function() use ($employeeIds, $validated, &$ingresados, &$omitidos, $request) {
                foreach ($employeeIds as $empId) {
                    if ($this->repository->findOverlap($empId, $validated['start_time'], $validated['end_time'])) {
                        $omitidos++; continue;
                    }
                    $data = $validated; $data['employee_id'] = $empId;
                    $this->repository->createIncidencia($data);
                    $ingresados++;
                }
            });

            return redirect()->route('incidencias.index')
                ->with('success', "¡Éxito! Registrados: $ingresados, Omitidos por traslape: $omitidos.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Fallo en proceso: ' . $e->getMessage());
        }
    }

    /**
     * Funciones para Inyección por Horario (NUEVAS)
     */
    public function createBySchedule()
    {
        return Inertia::render('Incidencias/CrearPorHorario', [
            'categories' => $this->repository->getLeaveCategories(),
        ]);
    }

    public function previewBySchedule(Request $request)
    {
        $validated = $request->validate([
            'filter_type' => 'required|in:entrada,salida,total',
            'start_time'  => 'required|date',
            'end_time'    => 'required|date',
        ]);

        try {
            $fecha = Carbon::parse($validated['start_time'])->toDateString();
            $hI = Carbon::parse($validated['start_time'])->format('H:i:s');
            $hF = Carbon::parse($validated['end_time'])->format('H:i:s');

            $empleadosRaw = $this->repository->getEmployeesBySchedule($fecha, $hI, $hF, $validated['filter_type']);
            $candidatos = collect($empleadosRaw)->unique('id')->values();

            return response()->json(['total' => $candidatos->count(), 'candidatos' => $candidatos]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function storeBySchedule(Request $request)
    {
        $validated = $request->validate([
            'filter_type' => 'required|in:entrada,salida,total',
            'category_id' => 'required|integer',
            'start_time'  => 'required|date',
            'end_time'    => 'required|date|after_or_equal:start_time',
            'reason'      => 'required|string|max:250',
        ]);

        try {
            $fecha = Carbon::parse($validated['start_time'])->toDateString();
            $hI = Carbon::parse($validated['start_time'])->format('H:i:s');
            $hF = Carbon::parse($validated['end_time'])->format('H:i:s');

            $empleados = collect($this->repository->getEmployeesBySchedule($fecha, $hI, $hF, $validated['filter_type']))->unique('id');
            if ($empleados->isEmpty()) return back()->with('error', "No se localizaron empleados para procesar.");

            $ingresados = 0; $omitidos = 0;
            DB::transaction(function() use ($empleados, $validated, &$ingresados, &$omitidos, $request) {
                foreach ($empleados as $emp) {
                    if ($this->repository->findOverlap($emp->id, $validated['start_time'], $validated['end_time'])) {
                        $omitidos++; continue; 
                    }
                    $payload = [
                        'employee_id' => $emp->id, 'category_id' => $validated['category_id'],
                        'start_time' => $validated['start_time'], 'end_time' => $validated['end_time'], 'reason' => $validated['reason']
                    ];
                    $this->repository->createIncidencia($payload);
                    $ingresados++;
                }
            });

            return redirect()->route('incidencias.index')->with('success', "Proceso terminado. Se inyectaron $ingresados incidencias.");
        } catch (Exception $e) {
            return back()->with('error', 'Error en el motor masivo: ' . $e->getMessage());
        }
    }

    /**
     * Estadísticas y Reportes Excel
     */
    public function statistics(Request $request)
    {
        try {
            $filtros = [
                'search' => $request->input('search'), 'general' => $request->boolean('general', false),
                'department_id' => $request->input('department_id'), 'ano' => $request->input('ano'),
                'date_start' => $request->input('date_start'), 'date_end' => $request->input('date_end'),
            ];
            return Inertia::render('Incidencias/Statistics', [
                'empleados'     => $this->repository->getEstadisticasGlobales($filtros),
                'departamentos' => $this->repository->getDepartamentos(),
                'filters'       => $filtros
            ]);
        } catch (Exception $e) {
            Log::error("Error Stats: " . $e->getMessage());
            return back()->withErrors(['error' => 'Error al cargar las estadísticas.']);
        }
    }

    public function exportStatistics(Request $request)
    {
        ini_set('memory_limit', '1024M');
        try {
            $filtros = $request->only(['search', 'department_id', 'ano', 'date_start', 'date_end']);
            $empleados = $this->repository->getEstadisticasParaExportar($filtros);
            if ($empleados->isEmpty()) return back()->withErrors(['error' => 'No hay datos.']);

            $empIds = $empleados->pluck('id')->toArray();
            $detallesPorEmpleado = collect($this->repository->getDetallesBulk($empIds, $filtros))->groupBy('employee_id');

            $resumen = []; $detalle = [];
            foreach ($empleados as $emp) {
                $items = $detallesPorEmpleado->get($emp->id, collect());
                if ($items->isEmpty()) continue;

                foreach ($items->groupBy('tipo') as $tipo => $rows) {
                    $resumen[] = [
                        'nomina' => $emp->emp_code, 'nombre' => "{$emp->first_name} {$emp->last_name}",
                        'tipo' => $tipo, 'dias' => $rows->sum('dias'), 'veces' => $rows->count(),
                        'primero'=> $rows->min('desde'), 'ultimo' => $rows->max('hasta'),
                    ];
                }
                foreach ($items as $d) {
                    $detalle[] = [
                        'nomina' => $emp->emp_code, 'nombre' => "{$emp->first_name} {$emp->last_name}",
                        'tipo' => $d->tipo, 'inicio' => $d->desde, 'final' => $d->hasta, 'motivo' => $d->motivo
                    ];
                }
            }

            return Excel::download(new IncidenciasEstadisticasExport(['resumen_categorias' => $resumen, 'listado_detallado' => $detalle], 'Inicio', 'Fin'), 'Reporte_Incidencias.xlsx');
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Fallo exportación.']);
        }
    }
}