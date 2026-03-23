<?php

namespace App\Models\Biotime\Original;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $connection = 'pgsql_original';
    protected $table = 'personnel_employee';
    public $timestamps = false;

    protected $casts = [
        'emp_code'          => 'string', // Bigint se trata como string para evitar pérdida de precisión
        'is_admin'          => 'boolean',
        'is_active'         => 'boolean',
        'is_truly_active'   => 'boolean',
        'enable_att'        => 'boolean',
        'deleted'           => 'boolean',
        'hire_date'         => 'date',
        'birthday'          => 'date',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function schedules()
    {
        return $this->hasMany(AttSchedule::class, 'employee_id');
    }
}