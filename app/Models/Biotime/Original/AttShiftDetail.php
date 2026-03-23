<?php

namespace App\Models\Biotime\Original;

use Illuminate\Database\Eloquent\Model;

class AttShiftDetail extends Model
{
    protected $connection = 'pgsql_original';
    protected $table = 'att_shiftdetail';
    public $timestamps = false;

    protected $fillable = [
        'shift_id', 
        'time_interval_id', 
        'day_index', 
        'in_time', 
        'out_time'
    ];

    protected $casts = [
        'in_time'  => 'string', // time without time zone
        'out_time' => 'string',
        'day_index' => 'integer',
    ];

    public function interval()
    {
        return $this->belongsTo(AttTimeInterval::class, 'time_interval_id');
    }
}