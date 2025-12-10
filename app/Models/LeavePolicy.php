<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeavePolicy extends Model
{
    protected $guarded = [];

    // Relación inversa: Una política pertenece a una categoría (ej. Vacaciones)
    public function category()
    {
        return $this->belongsTo(LeaveCategory::class, 'leave_category_id');
    }
}