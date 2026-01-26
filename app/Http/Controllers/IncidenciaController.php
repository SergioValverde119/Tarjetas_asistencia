<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Repositories\IncidenciaRepository; 
use Inertia\Inertia;
use Exception;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel; 
use App\Exports\IncidenciasResultExport; 
use App\Exports\IncidenciasTemplateExport; // <--- NUEVO IMPORT

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

            $incidencias = $this->repository->getIncidencias($search, $dateApply, $dateIncidence);
            $categorias = $this->repository->getLeaveCategories();

            return Inertia::render('Incidencias/Index', [
                'incidencias' => $incidencias,
                'categorias' => $categorias,
                'filters' => [
                    'search' => $search,
                    'date_apply' => $dateApply,
                    'date_incidence' => $dateIncidence
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
            $id = $this->repository->createIncidencia($validated);
            return redirect()->route('incidencias.index')->with('success', 'Incidencia registrada correctamente. Folio: ' . $id);
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

            $searchEmployee = $request->input('search');
            
            return Inertia::render('Incidencias/Edit', [
                'incidencia' => $incidencia,
                'employees'  => $this->repository->getActiveEmployees($searchEmployee),
                'categories' => $this->repository->getLeaveCategories(),
                'filters'    => [
                    'search' => $searchEmployee
                ]
            ]);
        } catch (Exception $e) {
            return redirect()->route('incidencias.index')->with('error', 'Error al cargar edición: ' . $e->getMessage());
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
            $this->repository->updateIncidencia($id, $validated);
            return redirect()->route('incidencias.index')->with('success', 'Incidencia actualizada correctamente.');
        } catch (Exception $e) {
            return back()->with('error', 'Error al actualizar: ' . $e->getMessage());
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
            return redirect()->back()->with('success', 'Nueva categoría creada exitosamente.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Error al crear categoría: ' . $e->getMessage());
        }
    }

    /**
     * --- DESCARGAR PLANTILLA LIMPIA ---
     */
    public function downloadTemplate()
    {
        // Plantilla con ejemplos realistas
        $plantilla = [
            // Fila 1: Búsqueda por Nómina
            [
                '1045',                // Nómina
                '',                    // Nombre (Vacío porque usamos nómina)
                'VAC',                 // Código
                '2025-02-01 09:00',    // Inicio
                '2025-02-02 18:00',    // Fin
                'Solicitud de vacaciones periodo 2024' // Motivo REAL
            ], 
            // Fila 2: Búsqueda por Nombre
            [
                '',                    // Nómina (Vacía porque usamos nombre)
                'Maria Gomez',         // Nombre
                'ENF',                 // Código
                '2025-03-01 09:00',    // Inicio
                '2025-03-01 18:00',    // Fin
                'Cita médica en el IMSS' // Motivo REAL
            ] 
        ];

        // Usamos el exportador dedicado a la plantilla
        return Excel::download(new IncidenciasTemplateExport($plantilla), 'plantilla_incidencias.xlsx');
    }

    /**
     * --- IMPORTACIÓN MASIVA INTELIGENTE ---
     */
    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv']);

        try {
            $data = Excel::toArray(new \stdClass, $request->file('file'));
            $rows = $data[0]; 

            // Remover encabezado si existe (detectando texto en la fila 1)
            // if (isset($rows[0][2]) && !is_numeric($rows[0][3]) && stripos($rows[0][2], 'cod') !== false) {
            //     array_shift($rows);
            // }

            array_shift($rows); 
            
            $resultados = [];

            foreach ($rows as $row) {
                // Mapeo de columnas según la plantilla nueva:
                // 0:Nómina, 1:Nombre, 2:CódigoPermiso, 3:Inicio, 4:Fin, 5:Motivo
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
                    // A. Validar Empleado (Prioridad: Nómina -> Nombre)
                    $empId = null;

                    if (!empty($empCode)) {
                        $empId = $this->repository->getEmployeeIdByCode($empCode);
                    }

                    if (!$empId && !empty($empName)) {
                        $empId = $this->repository->getEmployeeIdByName($empName);
                        if (!$empId) throw new Exception("No se encontró empleado con nombre: '$empName'");
                    } elseif (!$empId && !empty($empCode)) {
                        throw new Exception("No se encontró empleado con nómina: '$empCode'");
                    } elseif (!$empId) {
                        throw new Exception("Fila sin datos de empleado (se requiere Nómina o Nombre).");
                    }

                    // B. Validar Categoría
                    $catId = $this->repository->getCategoryIdByCode($catCode);
                    if (!$catId) throw new Exception("Tipo de permiso '$catCode' no existe.");

                    // C. Validar Fechas
                    try {
                        $start = Carbon::parse($startStr);
                        $end = Carbon::parse($endStr);
                        if ($end->lt($start)) throw new Exception("Fecha fin menor a inicio.");
                    } catch (Exception $e) {
                        throw new Exception("Formato de fecha inválido. Use AAAA-MM-DD HH:MM");
                    }

                    // D. Insertar
                    $this->repository->createIncidencia([
                        'employee_id' => $empId,
                        'category_id' => $catId,
                        'start_time' => $start->format('Y-m-d H:i:s'),
                        'end_time' => $end->format('Y-m-d H:i:s'),
                        'reason' => $reason
                    ]);

                    $status = 'INGRESADO';
                    $mensaje = 'OK';

                } catch (Exception $e) {
                    $status = 'NO INGRESADO';
                    $mensaje = $e->getMessage();
                }

                // Generamos fila para el reporte de resultados
                $resultados[] = [
                    $empCode,
                    $empName,
                    $catCode,
                    $startStr,
                    $endStr,
                    $reason,
                    $status,  // Columna G
                    $mensaje  // Columna H
                ];
            }

            $fileName = 'reporte_carga_' . date('Ymd_His') . '.xlsx';
            // Para el reporte de resultados, seguimos usando el exportador completo con colores
            return Excel::download(new IncidenciasResultExport($resultados), $fileName);

        } catch (Exception $e) {
            return response()->json(['message' => 'Error crítico al leer el archivo: ' . $e->getMessage()], 500);
        }
    }
}