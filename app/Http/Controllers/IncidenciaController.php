<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Repositories\IncidenciaRepository; 
use App\Models\LogModificacionIncidencia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Exception;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel; 
use App\Exports\IncidenciasResultExport; 
use App\Exports\IncidenciasTemplateExport;
use Illuminate\Validation\ValidationException;

/**
 * Controlador de Incidencias
 * Versión optimizada para Inyección Directa y Control de Traslapes.
 * Primeramente Jehová Dios y Jesús Rey.
 */
class IncidenciaController extends Controller
{
    protected $repository;

    public function __construct(IncidenciaRepository $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
        try {
            $search = $request->input('search');
            $dateApply = $request->input('date_apply');       
            $dateIncidence = $request->input('date_incidence'); 
            $dateStart = $request->input('date_start');         
            $dateEnd = $request->input('date_end'); 

            $incidencias = $this->repository->getIncidencias($search, $dateApply, $dateIncidence, $dateStart, $dateEnd);
            $categorias = $this->repository->getLeaveCategories();

            return Inertia::render('Incidencias/Index', [
                'incidencias' => $incidencias,
                'categorias' => $categorias,
                'filters' => [
                    'search' => $search,
                    'date_apply' => $dateApply,
                    'date_incidence' => $dateIncidence,
                    'date_start' => $dateStart,
                    'date_end' => $dateEnd
                ]
            ]);
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Error al cargar listado: ' . $e->getMessage());
        }
    }

    public function create(Request $request)
    {
        try {
            $searchEmployee = $request->input('search');          
            $searchCategory = $request->input('category_search'); 

            $employees = $this->repository->getActiveEmployees($searchEmployee);
            $categories = $this->repository->getLeaveCategories($searchCategory);

            return Inertia::render('Incidencias/Create', [
                'employees' => $employees,
                'categories' => $categories,
                'filters' => [
                    'search' => $searchEmployee,
                    'category_search' => $searchCategory
                ]
            ]);
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Error cargando datos: ' . $e->getMessage());
        }
    }

    /**
     * Registro Manual Individual
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer',
            'category_id' => 'required|integer',
            'start_time'  => 'required|date',
            'end_time'    => 'required|date|after_or_equal:start_time',
            'reason'      => 'nullable|string|max:250',
        ]);

        try {
            // 1. VALIDACIÓN DE TRASLAPE
            $overlap = $this->repository->findOverlap(
                $validated['employee_id'], 
                $validated['start_time'], 
                $validated['end_time']
            );

            if ($overlap) {
                throw ValidationException::withMessages([
                    'start_time' => "TRASLAPE: El empleado ya tiene un permiso (#{$overlap->abstractexception_ptr_id}) en esas fechas."
                ]);
            }

            // 2. INSERCIÓN DIRECTA EN BIO-TIME
            return DB::transaction(function () use ($request, $validated) {
                // El repositorio ahora hace todo el trabajo pesado de IDs y limpieza
                $id = $this->repository->createIncidencia($validated);

                // 3. BITÁCORA LOCAL LARAVEL
                LogModificacionIncidencia::create([
                    'user_id' => Auth::id(),
                    'tipo_accion' => 'CREACION',
                    'incidencia_id' => $id,
                    'valores_anteriores' => null,
                    'valores_nuevos' => $validated,
                    'ip_address' => $request->ip()
                ]);

                return redirect()->route('incidencias.index')->with('success', 'Incidencia guardada con éxito. Folio: ' . $id);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Error al guardar: ' . $e->getMessage());
        }
    }

    public function edit($id, Request $request)
    {
        try {
            $incidencia = $this->repository->findIncidenciaById($id);
            
            if (!$incidencia) {
                return redirect()->route('incidencias.index')->with('error', 'La incidencia no existe.');
            }

            return Inertia::render('Incidencias/Edit', [
                'incidencia' => $incidencia,
                'employees'  => $this->repository->getActiveEmployees($request->input('search')),
                'categories' => $this->repository->getLeaveCategories(),
                'filters'    => $request->only(['search', 'date_apply', 'date_incidence', 'date_start', 'date_end', 'page'])
            ]);
        } catch (Exception $e) {
            return redirect()->route('incidencias.index')->with('error', 'Error de sistema al cargar datos.');
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer',
            'category_id' => 'required|integer',
            'start_time'  => 'required|date',
            'end_time'    => 'required|date|after_or_equal:start_time',
            'reason'      => 'nullable|string|max:250'
        ]);

        try {
            $overlap = $this->repository->findOverlap(
                $validated['employee_id'], 
                $validated['start_time'], 
                $validated['end_time'],
                $id
            );

            if ($overlap) {
                throw ValidationException::withMessages([
                    'start_time' => "Error: Se traslapa con el permiso #{$overlap->abstractexception_ptr_id}."
                ]);
            }

            $filters = $request->only(['search', 'date_apply', 'date_incidence', 'page']);
            
            return DB::transaction(function () use ($request, $id, $validated, $filters) {
                $original = $this->repository->findIncidenciaById($id);
                if (!$original) throw new Exception("Registro no encontrado.");

                $valoresAnteriores = [
                    'employee_id' => $original->employee_id,
                    'category_id' => $original->category_id,
                    'start_time'  => $original->start_time,
                    'end_time'    => $original->end_time,
                    'reason'      => $original->apply_reason,
                ];

                $this->repository->updateIncidencia($id, $validated);

                LogModificacionIncidencia::create([
                    'user_id' => Auth::id(),
                    'tipo_accion' => 'EDICION',
                    'incidencia_id' => $id,
                    'valores_anteriores' => $valoresAnteriores,
                    'valores_nuevos' => $validated,
                    'ip_address' => $request->ip()
                ]);

                return redirect()->route('incidencias.index', $filters)->with('success', 'Modificación realizada con éxito.');
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            return back()->with('error', 'Error al actualizar: ' . $e->getMessage());
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $filters = $request->only(['search', 'date_apply', 'date_incidence', 'page']);
            
            return DB::transaction(function () use ($request, $id, $filters) {
                $original = $this->repository->findIncidenciaById($id);
                if (!$original) throw new Exception("El registro ya no existe.");

                $datosBorrados = [
                    'employee_id' => $original->employee_id,
                    'category_id' => $original->category_id,
                    'start_time'  => $original->start_time,
                    'end_time'    => $original->end_time,
                    'reason'      => $original->apply_reason,
                ];

                // Borrado directo en BioTime
                $this->repository->deleteIncidencia($id);

                LogModificacionIncidencia::create([
                    'user_id' => Auth::id(),
                    'tipo_accion' => 'ELIMINACION',
                    'incidencia_id' => $id,
                    'valores_anteriores' => $datosBorrados,
                    'valores_nuevos' => null, 
                    'ip_address' => $request->ip()
                ]);

                return redirect()->route('incidencias.index', $filters)->with('success', 'Incidencia eliminada permanentemente.');
            });
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Error al eliminar: ' . $e->getMessage());
        }
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:40',
            'code' => 'required|string|max:10',
            'unit' => 'required|integer|in:1,2,3',
        ]);

        try {
            $this->repository->createLeaveCategory($validated);
            return redirect()->back()->with('success', 'Nueva categoría creada.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Error al crear categoría: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        $plantilla = [
            ['1045', '', 'VAC', '2025-02-01 09:00', '2025-02-02 18:00', 'Vacaciones periodo 2024'], 
            ['', 'Maria Gomez', 'ENF', '2025-03-01 09:00', '2025-03-01 18:00', 'Cita médica'] 
        ];
        return Excel::download(new IncidenciasTemplateExport($plantilla), 'plantilla_incidencias.xlsx');
    }

    /**
     * Importación Masiva Excel con Escudo de Traslapes
     */
    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv']);

        try {
            $data = Excel::toArray(new \stdClass, $request->file('file'));
            $rows = $data[0]; 
            array_shift($rows); // Quitar encabezados
            
            $resultados = [];

            foreach ($rows as $row) {
                if (!isset($row[2])) continue;

                $empCode  = isset($row[0]) ? trim((string)$row[0]) : '';
                $empName  = isset($row[1]) ? trim((string)$row[1]) : '';
                $catCode  = trim((string)$row[2]);
                $startStr = trim((string)$row[3]);
                $endStr   = trim((string)$row[4]);
                $reason   = isset($row[5]) ? trim((string)$row[5]) : 'Carga Masiva Excel';
                
                $status = 'ERROR';
                $mensaje = '';

                try {
                    $empId = null;

                    // 1. Identificar empleado
                    if (!empty($empCode)) {
                        $empId = $this->repository->getEmployeeIdByCode($empCode);
                    }
                    if (!$empId && !empty($empName)) {
                        $empId = $this->repository->getEmployeeIdByName($empName);
                    }
                    if (!$empId) throw new Exception("Empleado no encontrado.");

                    // 2. Identificar categoría
                    $catId = $this->repository->getCategoryIdByCode($catCode);
                    if (!$catId) throw new Exception("Tipo de permiso '$catCode' inexistente.");

                    // 3. Validar Fechas
                    $start = Carbon::parse($startStr);
                    $end = Carbon::parse($endStr);
                    if ($end->lt($start)) throw new Exception("Fecha fin menor a inicio.");

                    // 4. ESCUDO DE TRASLAPE
                    $overlap = $this->repository->findOverlap($empId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'));
                    if ($overlap) throw new Exception("Traslape con Folio #{$overlap->abstractexception_ptr_id}");

                    // 5. INSERCIÓN TRANSACCIONAL
                    DB::transaction(function() use ($empId, $catId, $start, $end, $reason, $request) {
                        $newId = $this->repository->createIncidencia([
                            'employee_id' => $empId,
                            'category_id' => $catId,
                            'start_time' => $start->format('Y-m-d H:i:s'),
                            'end_time' => $end->format('Y-m-d H:i:s'),
                            'reason' => $reason
                        ]);

                        LogModificacionIncidencia::create([
                            'user_id' => Auth::id(),
                            'tipo_accion' => 'CREACION',
                            'incidencia_id' => $newId,
                            'valores_anteriores' => null,
                            'valores_nuevos' => [
                                'employee_id' => $empId, 'category_id' => $catId, 
                                'start_time' => $start->format('Y-m-d H:i:s'),
                                'end_time' => $end->format('Y-m-d H:i:s'), 'reason' => $reason
                            ],
                            'ip_address' => $request->ip()
                        ]);
                    });

                    $status = 'INGRESADO';
                    $mensaje = 'OK';

                } catch (Exception $e) {
                    $status = 'NO INGRESADO';
                    $mensaje = $e->getMessage();
                }

                $resultados[] = [$empCode, $empName, $catCode, $startStr, $endStr, $reason, $status, $mensaje];
            }

            return Excel::download(new IncidenciasResultExport($resultados), 'reporte_importacion_' . now()->format('Ymd_His') . '.xlsx');

        } catch (Exception $e) {
            return response()->json(['message' => 'Error al leer el archivo: ' . $e->getMessage()], 500);
        }
    }

    public function statistics(Request $request)
    {
        $filtros = [
            'search'     => $request->input('search'),
            'general'    => $request->boolean('general', false),
            'ano'        => $request->input('ano'),
            'date_start' => $request->input('date_start'),
            'date_end'   => $request->input('date_end'),
        ];

        // Si no hay nada, por defecto año actual
        if (empty($filtros['ano']) && empty($filtros['date_start'])) {
            $filtros['ano'] = date('Y');
        }

        $empleados = $this->repository->getEstadisticasGlobales($filtros);

        $empleados->getCollection()->transform(function ($emp) use ($filtros) {
            // Pasamos todos los filtros para que el detalle respete el rango
            $emp->detalles = $this->repository->getDetallePorEmpleado($emp->id, $filtros);
            return $emp;
        });

        return Inertia::render('Incidencias/Statistics', [
            'empleados' => $empleados,
            'filters'   => $filtros
        ]);
    }
}