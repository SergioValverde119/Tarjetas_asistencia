<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * NOTA TÉCNICA: 
 * Estructura de LLAVE-VALOR para la tabla 'settings'.
 * Este modelo permite gestionar reglas de negocio de forma dinámica.
 */
class Configuracion extends Model
{
    protected $table = 'settings';

    protected $fillable = ['key', 'value'];

    /**
     * MÉTODO ESTÁTICO PARA OBTENER VALORES
     * Incluye un sistema de caché de 24 horas para optimizar el rendimiento.
     */
    public static function get(string $key, $default = null)
    {
        return Cache::remember("setting_{$key}", 86400, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * MÉTODO ESTÁTICO PARA GUARDAR O ACTUALIZAR VALORES
     */
    public static function set(string $key, $value)
    {
        $setting = self::updateOrCreate(
            ['key' => $key], 
            ['value' => (string) $value]
        );
        
        Cache::forget("setting_{$key}");
        
        return $setting;
    }

    /**
     * REGLAS DE ASISTENCIA UNIFICADAS
     * Cada vez que crees una nueva configuración, agrégala aquí
     * para que el controlador y la vista la reconozcan automáticamente.
     */
    public static function getAllRules()
    {
        return [
            'tolerancia_entrada'       => (int) self::get('tolerancia_entrada', 10),
            'limite_retardo_leve'      => (int) self::get('limite_retardo_leve', 15),
            'limite_retardo_grave'     => (int) self::get('limite_retardo_grave', 40),
            'conteo_rl_para_rg'        => (int) self::get('conteo_rl_para_rg', 4),
            'minutos_falta_automatica' => (int) self::get('minutos_falta_automatica', 41),
            // Ejemplo de nueva regla:
            // 'limite_faltas_mensuales' => (int) self::get('limite_faltas_mensuales', 3),
        ];
    }
}