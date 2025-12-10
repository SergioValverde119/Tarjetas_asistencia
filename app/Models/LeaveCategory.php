<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveCategory extends Model
{
    protected $guarded = []; // Permite asignación masiva

    // Una categoría tiene muchas reglas/políticas
    public function policies()
    {
        return $this->hasMany(LeavePolicy::class);
    }
}