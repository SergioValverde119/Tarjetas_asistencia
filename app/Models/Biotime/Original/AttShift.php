<?php

namespace App\Models\Biotime\Original;

use Illuminate\Database\Eloquent\Model;

class AttShift extends Model
{
    protected $connection = 'pgsql_original';
    protected $table = 'att_attshift';
    public $timestamps = false;

    protected $fillable = ['alias', 'status'];

    protected $casts = [
        'work_weekend' => 'boolean',
        'work_day_off' => 'boolean',
        'auto_shift'   => 'boolean',
        'status'       => 'integer',
    ];

    public function detalles()
    {
        return $this->hasMany(AttShiftDetail::class, 'shift_id');
    }
}