<?php

namespace App\Models\Biotime\Original;

use Illuminate\Database\Eloquent\Model;

class AttSchedule extends Model
{
    protected $connection = 'pgsql_original';
    protected $table = 'att_attschedule';
    public $timestamps = false;

    protected $fillable = [
        'employee_id', 
        'shift_id', 
        'start_date', 
        'end_date'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function shift()
    {
        return $this->belongsTo(AttShift::class, 'shift_id');
    }
}