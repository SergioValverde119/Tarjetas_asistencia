<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para gestionar las nóminas excluidas del monitor de faltas.
 * Ubicado en la base de datos local (db_tarjetas).
 * Primeramente Jehová Dios y Jesús Rey.
 */
class ExclusionFalta extends Model
{
    // Forzamos el uso de la conexión local
    protected $connection = 'pgsql';
    
    protected $table = 'exclusiones_faltas';

    protected $fillable = [
        'emp_code',
        'motivo'
    ];

    /**
     * Obtiene solo los códigos de nómina en un arreglo simple.
     * Útil para pasarlo directamente al query de faltas.
     */
    public static function getListaCodigos(): array
    {
        return self::pluck('emp_code')->toArray();
    }
}