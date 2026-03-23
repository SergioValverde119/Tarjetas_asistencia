<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Servicio para interactuar con la API de BioTime 8.5/9.0
 * Versión optimizada: Solo checadas y sin etiquetas de sistema externo.
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
        $this->username = env('BIOTIME_API_USER', 'api');
        $this->password = env('BIOTIME_API_PASSWORD', 'Axelaxel1.');
    }

    /**
     * Autenticación JWT.
     */
    public function login()
    {
        // Si ya tenemos un token cargado de forma manual en el constructor, lo usamos.
        if ($this->token) {
            return $this->token;
        }

        try {
            // El endpoint correcto proporcionado es jwt-api-token-auth/
            $response = Http::post("{$this->baseUrl}/jwt-api-token-auth/", [
                'username' => $this->username,
                'password' => $this->password,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // BioTime suele devolver el token en la llave 'token' o 'jwt'
                $this->token = $data['token'] ?? ($data['jwt'] ?? null);
                
                if ($this->token) {
                    return $this->token;
                }
            }
            
            Log::error("Falla de login BioTime: " . $response->body());
            return null;
            
        } catch (Exception $e) {
            Log::error("Error login API BioTime: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Crear una Checada/Transacción vía API.
     * Se han eliminado las etiquetas fijas para que parezca un registro original del reloj.
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
                'verify_type'    => (int) ($data['verify_type'] ?? 1), // 1 = Huella por defecto
                'work_code'      => (string) ($data['work_code'] ?? '0'),
                'terminal_sn'    => $data['terminal_sn'] ?? null,
                'terminal'       => (int) ($data['terminal_id'] ?? null),
                'terminal_alias' => $data['terminal_alias'] ?? null,
                'area_alias'     => $data['area_alias'] ?? null, // Eliminado 'SISTEMA_WEB'
                'temperature'    => "0.0",
                'source'         => 1,
                'purpose'        => 9
            ];

            $response = Http::withToken($this->token, 'JWT')
                ->post("{$this->baseUrl}/iclock/api/transactions/", $payload);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            return ['success' => false, 'error' => $response->json() ?? $response->body()];

        } catch (Exception $e) {
            Log::error("Error inyectando checada: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}