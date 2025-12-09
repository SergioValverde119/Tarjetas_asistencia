<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;

class ReglasController extends Controller
{
    public function index()
    {
        // 1. Obtenemos ID, Símbolo y Nombre de BioTime
        $categoriasBioTime = DB::connection('pgsql_biotime')
            ->table('att_leavecategory')
            ->orderBy('category_name')
            ->get(['id', 'report_symbol', 'category_name']);

        // 2. Nuestro diccionario ahora mapea: ID (Key) => CATEGORIA (Value)
        // Esto es clave: usamos el ID como índice
        $mapeosGuardados = DB::table('mapeo_de_permisos')
            ->pluck('nuestra_categoria', 'biotime_id');

        // 3. Opciones Limpias
        $opcionesDeMapeo = [
            'VACACION', 'INCAPACIDAD', 'PERMISO_CON_GOCE', 
            'PERMISO_SIN_GOCE', 'PERMISO_MATERNIDAD', 
            'PERMISO_PATERNIDAD', 'FALTA_JUSTIFICADA', 'OTRO'
        ];
        
        $limiteFaltas = DB::table('settings')->where('key', 'limite_faltas')->value('value');

        return Inertia::render('Reglas/Index', [
            'categoriasBioTime' => $categoriasBioTime,
            'mapeosGuardados' => $mapeosGuardados,
            'opcionesDeMapeo' => $opcionesDeMapeo,
            'limiteFaltas' => (int)($limiteFaltas ?? 3),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'limite_faltas' => 'required|integer|min:1',
            'mapeos' => 'required|array',
            'mapeos.*.id' => 'required|integer', // Validamos el ID
            'mapeos.*.category_name' => 'required|string',
            'mapeos.*.nuestra_categoria' => 'required|string',
        ]);

        DB::transaction(function () use ($request) {
            
            DB::table('settings')->updateOrInsert(
                ['key' => 'limite_faltas'],
                ['value' => $request->input('limite_faltas'), 'updated_at' => now()]
            );

            $mapeos = $request->input('mapeos');

            foreach ($mapeos as $mapeo) {
                // Usamos el ID como clave única para guardar en la nueva tabla
                DB::table('mapeo_de_permisos')->updateOrInsert(
                    ['biotime_id' => $mapeo['id']], 
                    [
                        'biotime_name' => $mapeo['category_name'],
                        'nuestra_categoria' => $mapeo['nuestra_categoria'],
                        'updated_at' => now(),
                    ]
                );
            }
        });

        return Redirect::route('reglas.index')->with('success', '¡Reglas guardadas correctamente!');
    }
}