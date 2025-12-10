<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;
use App\Models\LeaveCategory;
use App\Models\LeavePolicy;
use App\Models\LeaveMapping;

class ReglasController extends Controller
{
    public function index()
    {
        // 1. Datos de BioTime (Etiquetas Sucias)
        $categoriasBioTime = DB::connection('pgsql_biotime')
            ->table('att_leavecategory')
            ->orderBy('category_name')
            ->get(['id', 'report_symbol', 'category_name']);

        // 2. Nóminas Disponibles
        $nominas = DB::connection('pgsql_biotime')
            ->table('personnel_area')
            ->where('area_name', '!=', 'Default (Reservado)')
            ->where('area_name', '!=', 'SEDUVI')
            ->orderBy('area_name')
            ->get(['id', 'area_name']);

        // 3. Nuestras Categorías y Políticas
        $misCategorias = LeaveCategory::all();

        // Cargamos políticas con relaciones
        $misPoliticas = LeavePolicy::with('category')->get()
            ->map(function ($pol) use ($nominas) {
                if ($pol->area_id) {
                    $area = $nominas->firstWhere('id', $pol->area_id);
                    $pol->area_name = $area ? $area->area_name : 'Nómina Desconocida';
                } else {
                    $pol->area_name = 'TODAS (Global)';
                }
                return $pol;
            });

        // 4. Mapeo Actual
        $mapeosGuardados = DB::table('leave_mappings')
            ->pluck('leave_category_id', 'biotime_leave_id');
        
        $limiteFaltas = DB::table('settings')->where('key', 'limite_faltas')->value('value');

        return Inertia::render('Reglas/Index', [
            'categoriasBioTime' => $categoriasBioTime,
            'misCategorias' => $misCategorias,
            'misPoliticas' => $misPoliticas,
            'nominas' => $nominas,
            'mapeosGuardados' => $mapeosGuardados,
            'limiteFaltas' => (int)($limiteFaltas ?? 3),
        ]);
    }

    // --- ¡NUEVO! CREAR CATEGORÍA ---
    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:leave_categories,name',
            'color' => 'required|string',
            'is_paid' => 'boolean'
        ]);

        LeaveCategory::create($validated);

        return back()->with('success', 'Categoría creada correctamente.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'limite_faltas' => 'required|integer|min:1',
            'mapeos' => 'required|array',
            'mapeos.*.id' => 'required|integer', 
            'mapeos.*.leave_category_id' => 'nullable|exists:leave_categories,id',
        ]);

        DB::transaction(function () use ($request) {
            
            DB::table('settings')->updateOrInsert(
                ['key' => 'limite_faltas'],
                ['value' => $request->input('limite_faltas'), 'updated_at' => now()]
            );

            $mapeos = $request->input('mapeos');

            foreach ($mapeos as $mapeo) {
                if (empty($mapeo['leave_category_id'])) {
                    DB::table('leave_mappings')->where('biotime_leave_id', $mapeo['id'])->delete();
                    continue;
                }

                DB::table('leave_mappings')->updateOrInsert(
                    ['biotime_leave_id' => $mapeo['id']],
                    [
                        'leave_category_id' => $mapeo['leave_category_id'],
                        'biotime_name_snapshot' => $mapeo['category_name'] ?? 'Desconocido',
                        'updated_at' => now(),
                    ]
                );
            }
        });

        return Redirect::route('reglas.index')->with('success', 'Mapeo y configuración guardados.');
    }

    public function storePolicy(Request $request)
    {
        $validated = $request->validate([
            'leave_category_id' => 'required|exists:leave_categories,id',
            'area_id' => 'nullable', // Puede ser null
            'limit_amount' => 'required|integer|min:1',
            'frequency' => 'required|in:ANUAL,SEMESTRAL,MENSUAL,QUINCENAL',
        ]);

        // Si area_id viene como string "null" o vacío, lo convertimos a null real
        $areaId = $validated['area_id'] ? (int)$validated['area_id'] : null;

        LeavePolicy::updateOrCreate(
            [
                'leave_category_id' => $validated['leave_category_id'],
                'area_id' => $areaId,
                'frequency' => $validated['frequency'],
            ],
            [
                'limit_amount' => $validated['limit_amount']
            ]
        );

        return back()->with('success', 'Regla de política guardada.');
    }

    public function deletePolicy($id)
    {
        LeavePolicy::destroy($id);
        return back()->with('success', 'Política eliminada.');
    }
}