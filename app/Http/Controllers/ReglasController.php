<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;

class ReglasController extends Controller
{
    /**
     * Muestra la página de mapeo de reglas.
     */
    public function index()
    {
        // 1. Obtenemos las categorías "sucias" de la BD de BioTime
        $categoriasBioTime = DB::connection('pgsql_biotime')
            ->table('att_leavecategory')
            ->orderBy('category_name')
            ->get(['report_symbol', 'category_name']);

        // 2. Obtenemos nuestro "diccionario" (lo que ya guardamos)
        // Lo convertimos en un mapa (ej: 'VAC' => 'VACACION') para que sea fácil de leer
        $mapeosGuardados = DB::table('mapeo_de_permisos')
            ->pluck('nuestra_categoria', 'biotime_report_symbol');

        // 3. Opciones "limpias" que puede elegir el usuario
        $opcionesDeMapeo = [
            'VACACION',
            'PERMISO_CON_GOCE',
            'PERMISO_SIN_GOCE',
            'INCAPACIDAD',
            'FALTA_JUSTIFICADA',
            'OTRO'
        ];
        
        // 4. Renderizamos la página de Vue
        return Inertia::render('Reglas/Index', [
            'categoriasBioTime' => $categoriasBioTime,
            'mapeosGuardados' => $mapeosGuardados,
            'opcionesDeMapeo' => $opcionesDeMapeo
        ]);
    }

    /**
     * Guarda las nuevas reglas de mapeo.
     */
    public function store(Request $request)
    {
        // Validamos que los datos vengan en el formato correcto
        $request->validate([
            'mapeos' => 'required|array',
            'mapeos.*.report_symbol' => 'required|string',
            'mapeos.*.category_name' => 'required|string',
            'mapeos.*.nuestra_categoria' => 'required|string',
        ]);

        $mapeos = $request->input('mapeos');

        foreach ($mapeos as $mapeo) {
            DB::table('mapeo_de_permisos')->updateOrInsert(
                // Si la 'clave' (report_symbol) ya existe...
                ['biotime_report_symbol' => $mapeo['report_symbol']],
                // ...la actualiza. Si no, la inserta.
                [
                    'biotime_category_name' => $mapeo['category_name'],
                    'nuestra_categoria' => $mapeo['nuestra_categoria'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        return Redirect::route('reglas.index')->with('success', '¡Reglas guardadas con éxito!');
    }
}
