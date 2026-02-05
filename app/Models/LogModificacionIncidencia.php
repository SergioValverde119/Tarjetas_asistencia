<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogModificacionIncidencia extends Model
{
    /**
     * Nombre de la tabla asociada en la base de datos de Laravel.
     */
    protected $table = 'log_modificaciones_incidencias';

    /**
     * Campos que permiten asignación masiva.
     */
    protected $fillable = [
        'user_id',           // Administrador que hace el cambio
        'tipo_accion',       // CREACION, EDICION o ELIMINACION
        'incidencia_id',     // ID de la tabla att_leave de BioTime
        'valores_anteriores',// Datos previos (en formato JSON)
        'valores_nuevos',    // Datos guardados (en formato JSON)
        'ip_address'         // IP de origen por seguridad
    ];

    /**
     * Conversión automática de tipos (Casting).
     * Esto permite manipular los campos JSON como si fueran arreglos de PHP.
     */
    protected $casts = [
        'valores_anteriores' => 'array',
        'valores_nuevos' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Obtiene el usuario de Laravel que realizó la acción registrada.
     * Permite hacer: $log->user->name
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}