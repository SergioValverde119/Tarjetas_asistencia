<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;

class ReglasController extends Controller
{
    /**
     * Muestra la página de mapeo de reglas y configuración.
     */
    public function index()
    {
        // 1. Obtenemos las categorías "sucias" DIRECTO de la BD de BioTime (Solo lectura)
        // Usamos la conexión 'pgsql_biotime' que definimos en config/database.php
        $categoriasBioTime = DB::connection('pgsql_biotime')
            ->table('att_leavecategory')
            ->orderBy('category_name')
            ->get(['report_symbol', 'category_name']);

        // 2. Obtenemos nuestro "diccionario" (lo que ya guardamos en NUESTRA BD local)
        // Lo convertimos en una lista simple: 'SímboloBioTime' => 'NuestraCategoría'
        // Esto lee de la conexión por defecto ('pgsql' / bd_tarjetas)
        $mapeosGuardados = DB::table('mapeo_de_permisos')
            ->pluck('nuestra_categoria', 'biotime_report_symbol');

        // 3. Estas son las opciones "limpias" que teniamos antes (RESTITUIDAS)
        $opcionesDeMapeo = [
            'VACACION',
            'PERMISO_CON_GOCE',
            'PERMISO_SIN_GOCE',
            'INCAPACIDAD',
            'FALTA_JUSTIFICADA',
            'OTRO' // Para cosas que no suman ni restan
        ];

        // 4. Obtenemos el límite de faltas configurado (o usamos 3 por defecto)
        $limiteFaltas = DB::table('settings')->where('key', 'limite_faltas')->value('value');
        
        // 5. Enviamos todo a la vista de Vue
        return Inertia::render('Reglas/Index', [
            'categoriasBioTime' => $categoriasBioTime,
            'mapeosGuardados' => $mapeosGuardados,
            'opcionesDeMapeo' => $opcionesDeMapeo,
            'limiteFaltas' => (int)($limiteFaltas ?? 3), // Enviamos como entero
        ]);
    }

    /**
     * Guarda los cambios en el diccionario y la configuración.
     */
    public function store(Request $request)
    {
        // Validamos que los datos vengan en el formato correcto
        $request->validate([
            'limite_faltas' => 'required|integer|min:1', // Validación para el límite
            'mapeos' => 'required|array',
            'mapeos.*.report_symbol' => 'nullable|string', // Puede venir vacío si BioTime no tiene símbolo
            'mapeos.*.category_name' => 'required|string',
            'mapeos.*.nuestra_categoria' => 'required|string',
        ]);

        // Usamos una transacción para que se guarde todo o nada (seguridad de datos)
        DB::transaction(function () use ($request) {
            
            // 1. GUARDAR EL LÍMITE DE FALTAS
            DB::table('settings')->updateOrInsert(
                ['key' => 'limite_faltas'],
                [
                    'value' => $request->input('limite_faltas'),
                    'updated_at' => now()
                ]
            );

            // 2. GUARDAR LOS MAPEÓS DE REGLAS
            $mapeos = $request->input('mapeos');

            foreach ($mapeos as $mapeo) {
                // Usamos updateOrInsert para no duplicar registros.
                // La clave única es el 'report_symbol' original de BioTime.
                
                // Nota: Si el símbolo viene vacío (ej. null o ""), usamos una cadena vacía ""
                // para que la base de datos no se queje.
                $symbol = $mapeo['report_symbol'] ?? '';

                DB::table('mapeo_de_permisos')->updateOrInsert(
                    ['biotime_report_symbol' => $symbol],
                    [
                        'biotime_category_name' => $mapeo['category_name'],
                        'nuestra_categoria' => $mapeo['nuestra_categoria'],
                        'updated_at' => now(),
                    ]
                );
            }
        });

        // Redireccionamos con un mensaje flash de éxito
        return Redirect::route('reglas.index')->with('success', '¡Configuración y reglas guardadas con éxito!');
    }
}