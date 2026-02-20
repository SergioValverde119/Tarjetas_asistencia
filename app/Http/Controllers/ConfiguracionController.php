<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConfiguracionController extends Controller
{
    /**
     * Muestra el panel de configuración con los valores actuales.
     */
    public function index()
    {
        return Inertia::render('Admin/Configuracion/Index', [
            'config' => Configuracion::getAllRules(),
            'flash'  => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    /**
     * Actualiza las configuraciones en la tabla 'settings'.
     */
    public function update(Request $request)
    {
        // 1. Validamos los datos según las reglas de negocio
        $validated = $request->validate([
            'tolerancia_entrada'       => 'required|integer|min:0|max:59',
            'limite_retardo_leve'      => 'required|integer|min:1|max:59',
            'limite_retardo_grave'     => 'required|integer|min:1|max:120',
            'conteo_rl_para_rg'        => 'required|integer|min:1|max:10',
            'minutos_falta_automatica' => 'required|integer|min:1|max:240',
        ]);

        try {
            // 2. Guardamos cada valor usando el método set() del modelo
            // Esto actualiza la BD y limpia la caché automáticamente
            foreach ($validated as $key => $value) {
                Configuracion::set($key, $value);
            }

            return redirect()->back()->with('success', 'Configuraciones de asistencia actualizadas correctamente.');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al guardar la configuración: ' . $e->getMessage());
        }
    }
}