<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Repositories\IncidenciaRepository; 
use Inertia\Inertia;
use Exception;

class IncidenciaController extends Controller
{
    protected $repository;

    public function __construct(IncidenciaRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * --- LISTADO DE INCIDENCIAS (Con Filtros) ---
     */
    public function index(Request $request)
    {
        try {
            // Capturamos los filtros de la URL
            $search = $request->input('search');
            $dateApply = $request->input('date_apply');       // Fecha Registro
            $dateIncidence = $request->input('date_incidence'); // Fecha Incidencia

            // Pasamos los filtros al repositorio
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
}