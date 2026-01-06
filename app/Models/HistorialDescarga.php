<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistorialDescarga extends Model
{
    use HasFactory;

    // Nombre de la tabla (opcional si sigues la convención, pero bueno para ser explícito)
    protected $table = 'historial_descargas';

    // Campos que permitimos llenar masivamente
    protected $fillable = [
        'user_id',
        'month',
        'year',
        'downloaded_at',
        'ip_address'
    ];

    /**
     * Relación: Un historial pertenece a un Usuario.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}