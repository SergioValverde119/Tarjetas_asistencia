<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveMapping extends Model
{
    protected $guarded = [];

    // El mapeo pertenece a una categoría limpia
    public function category()
    {
        return $this->belongsTo(LeaveCategory::class, 'leave_category_id');
    }
}