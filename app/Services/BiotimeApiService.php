<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Servicio para interactuar con la API de BioTime 8.5/9.0
 * Primeramente Jehová Dios y Jesús Rey.
 */
class BiotimeApiService
{
    protected $baseUrl;
    protected $token;
    protected $username;
    protected $password;

    public function __construct()
    {
        $this->baseUrl  = rtrim(env('BIOTIME_API_URL', 'http://10.37.1.6:8024'), '/');
        $this->token    = env('BIOTIME_API_TOKEN', null); 
        $this->username = env('BIOTIME_API_USER', 'admin');
        $this->password = env('BIOTIME_API_PASSWORD', 'admin');
    }

    public function login()
    {
        if ($this->token) {
            return $this->token;
        }

        try {
            $response = Http::post("{$this->baseUrl}/api-token-auth/", [
                'username' => $this->username,
                'password' => $this->password,
            ]);

            if ($response->successful()) {
                $this->token = $response->json()['token'];
                return $this->token;
            }
            
            throw new Exception("Error de login en BioTime: " . $response->body());
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return null;
        }
    }

    /**
     * Crear un Permiso/Incidencia (Leave) vía API
     * AJUSTE DEFINITIVO: Nombres de campos según el error de validación JSON.
     */
    public function crearPermiso($data)
    {
        if (!$this->token) { $this->login(); }

        try {
            // BioTime espera: category (ID), start_time, end_time, employee (ID)
            $payload = [
                'employee'        => (int) $data['employee_id'], 
                'category'        => (int) $data['leave_type_id'], // Corregido: antes era 'type'
                'start_time'      => $data['fecha_inicio'],        // Corregido: antes era 'start_date'
                'end_time'        => $data['fecha_fin'],           // Corregido: antes era 'end_date'
                'reason'          => $data['motivo'] ?? 'Generado desde Sistema Externo',
                'status'          => 1, // Aprobado
                'type'            => 1, // Tipo de excepción (1 = Leave/Permiso)
                'vacation_number' => $data['vacation_number'] ?? 0, 
            ];

            $response = Http::withToken($this->token, 'JWT')
                ->post("{$this->baseUrl}/att/api/leaves/", $payload);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            return ['success' => false, 'error' => $response->json() ?? $response->body()];

        } catch (Exception $e) {
            Log::error("Error creando permiso vía API: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Borrar un Permiso/Incidencia (Leave) vía API
     */
    public function borrarPermiso($id)
    {
        if (!$this->token) { $this->login(); }

        try {
            $response = Http::withToken($this->token, 'JWT')
                ->delete("{$this->baseUrl}/att/api/leaves/{$id}/");

            if ($response->successful()) {
                return ['success' => true];
            }

            return ['success' => false, 'error' => $response->body()];

        } catch (Exception $e) {
            Log::error("Error borrando permiso vía API: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Crear una Checada/Transacción vía API
     */
    public function crearChecada($data)
    {
        if (!$this->token) { $this->login(); }

        try {
            $payload = [
                'emp_code'       => (string) $data['emp_code'],
                'emp'            => (int) $data['emp_id'],
                'punch_time'     => $data['punch_time'],
                'upload_time'    => now()->format('Y-m-d H:i:s'),
                'punch_state'    => (string) $data['punch_state'],
                'verify_type'    => 1, 
                'work_code'      => '0',
                'terminal_sn'    => $data['terminal_sn'] ?? 'API_IMPORT',
                'terminal'       => (int) ($data['terminal_id'] ?? 1),
                'terminal_alias' => $data['terminal_alias'] ?? 'Importación API',
                'area_alias'     => 'SISTEMA_WEB',
                'temperature'    => "0.0",
                'source'         => 1,
                'purpose'        => 9
            ];

            $response = Http::withToken($this->token, 'JWT')
                ->post("{$this->baseUrl}/iclock/api/transactions/", $payload);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            return ['success' => false, 'error' => $response->body()];

        } catch (Exception $e) {
            Log::error("Error creando checada vía API: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Obtener tipos de permisos
     */
    public function obtenerTiposDePermisos()
    {
        if (!$this->token) { $this->login(); }

        $response = Http::withToken($this->token, 'JWT')->get("{$this->baseUrl}/att/api/leave-types/");
        return $response->successful() ? $response->json() : [];
    }
}