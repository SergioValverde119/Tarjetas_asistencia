<?php

namespace App\Models\Biotime\Original;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $connection = 'pgsql_original';
    protected $table = 'personnel_department';
    public $timestamps = false;

    protected $fillable = [
        'dept_code',
        'dept_name',
        'is_default',
        'company_id',
        'parent_dept_id'
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Relación con el departamento padre
     */
    public function parent()
    {
        return $this->belongsTo(Department::class, 'parent_dept_id');
    }

    /**
     * Relación con sub-departamentos
     */
    public function children()
    {
        return $this->hasMany(Department::class, 'parent_dept_id');
    }

    /**
     * Relación con empleados
     */
    public function employees()
    {
        return $this->hasMany(Employee::class, 'department_id');
    }
}