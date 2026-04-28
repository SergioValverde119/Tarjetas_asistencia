<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Modelo User - Gestión de Usuarios del Sistema.
 * Estandarizado a minúsculas con protección de integridad para la columna 'name'.
 * Primeramente Jehová Dios y Jesús Rey.
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Atributos asignables masivamente.
     * Organizados en lista para mejor legibilidad.
     */
    protected $fillable = [
        'nombre',
        'paterno',
        'materno',
        'name',
        'username',
        'email',
        'password',
        'role',
        'biotime_id',
        'emp_code',
        'rfc',
        'curp',
    ];

    protected $hidden = [
        'password', 
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // ========================================================================
    // MUTATORS (Escritura)
    // ========================================================================

    public function setUsernameAttribute($value) 
    { 
        $this->attributes['username'] = $value ? strtolower(trim($value)) : null; 
        // Si el nombre completo está vacío, intentamos resincronizar usando este username
        $this->syncFullName();
    }

    public function setEmailAttribute($value) 
    { 
        $this->attributes['email'] = $value ? strtolower(trim($value)) : null; 
    }

    public function setNombreAttribute($value) 
    { 
        $this->attributes['nombre'] = $value ? strtolower(trim($value)) : null; 
        $this->syncFullName(); 
    }

    public function setPaternoAttribute($value) 
    { 
        $this->attributes['paterno'] = $value ? strtolower(trim($value)) : null; 
        $this->syncFullName(); 
    }

    public function setMaternoAttribute($value) 
    { 
        $this->attributes['materno'] = $value ? strtolower(trim($value)) : null; 
        $this->syncFullName(); 
    }

    public function setRfcAttribute($value) 
    { 
        $this->attributes['rfc'] = $value ? strtolower(trim($value)) : null; 
    }

    public function setCurpAttribute($value) 
    { 
        $this->attributes['curp'] = $value ? strtolower(trim($value)) : null; 
    }

    /**
     * LÓGICA DE PROTECCIÓN PARA COLUMNA 'NAME' (NOT NULL):
     * Genera el nombre completo en minúsculas. 
     * Si no hay nombre/apellidos, usa el username como salvavidas para evitar errores de BD.
     */
    protected function syncFullName()
    {
        $nombre  = $this->attributes['nombre'] ?? '';
        $paterno = $this->attributes['paterno'] ?? '';
        $materno = $this->attributes['materno'] ?? '';
        
        $fullName = trim("$nombre $paterno $materno");
        
        if ($fullName !== '') {
            $this->attributes['name'] = strtolower($fullName);
        } else {
            // FALLBACK: Si no hay identidad, usamos el username (que es obligatorio)
            // para que la columna 'name' de la base de datos nunca reciba un NULL.
            $this->attributes['name'] = $this->attributes['username'] ?? 'usuario_nuevo';
        }
    }

    // ========================================================================
    // SCOPES (Lectura)
    // ========================================================================

    public function scopeBuscar($query, $term)
    {
        if (!$term) return $query;
        $termLower = strtolower(trim($term));
        return $query->where(function($q) use ($termLower) {
            $q->where('name', 'like', "%$termLower%")
              ->orWhere('username', 'like', "%$termLower%")
              ->orWhere('email', 'like', "%$termLower%")
              ->orWhere('emp_code', 'like', "%$termLower%")
              ->orWhere('rfc', 'like', "%$termLower%")
              ->orWhere('curp', 'like', "%$termLower%");
        });
    }
}