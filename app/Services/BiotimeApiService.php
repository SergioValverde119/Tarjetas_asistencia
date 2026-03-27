<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Servicio para interactuar con la API de BioTime 8.5/9.0
 * Versión corregida: Manejo automático de expiración de firma JWT (signature has expired).
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
        // El token del ENV es el inicial, pero el servicio puede renovarlo en tiempo de ejecución
        $this->token    = env('BIOTIME_API_TOKEN', null); 
        $this->username = env('BIOTIME_API_USER', 'api');
        $this->password = env('BIOTIME_API_PASSWORD', 'Axelaxel1.');
    }

    /**
     * Autenticación JWT.
     * @param bool $force Si es true, ignora el token actual y pide uno nuevo al servidor.
     */
    public function login($force = false)
    {
        // Si ya tenemos un token y no estamos forzando renovación, lo usamos
        if ($this->token && !$force) {
            return $this->token;
        }

        try {
            $response = Http::post("{$this->baseUrl}/jwt-api-token-auth/", [
                'username' => $this->username,
                'password' => $this->password,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // BioTime devuelve el token en 'token' o 'jwt'
                $newToken = $data['token'] ?? ($data['jwt'] ?? null);
                
                if ($newToken) {
                    $this->token = $newToken;
                    // Opcional: Podrías guardar este token en Cache para no loguear en cada petición
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
     * Maneja el reintento automático si detecta "signature has expired".
     */
    public function crearChecada($data, $isRetry = false)
    {
        // Asegurar que tenemos un token inicial
        if (!$this->token) { 
            $this->login(); 
        }

        if (!$this->token) {
            return ['success' => false, 'error' => 'No se pudo obtener el token de autenticación.'];
        }

        try {
            $payload = [
                'emp_code'       => (string) $data['emp_code'],
                'emp'            => (int) $data['emp_id'],
                'punch_time'     => $data['punch_time'],
                'upload_time'    => now()->format('Y-m-d H:i:s'),
                'punch_state'    => (string) ($data['punch_state'] ?? '0'),
                'verify_type'    => (int) ($data['verify_type'] ?? 1), 
                'work_code'      => (string) ($data['work_code'] ?? '0'),
                'terminal_sn'    => $data['terminal_sn'] ?? null,
                'terminal'       => (int) ($data['terminal_id'] ?? null),
                'temperature'    => "0.0",
                'source'         => 1,
                'purpose'        => 9
            ];

            // BioTime requiere el prefijo "JWT " (con espacio) en el header de Authorization
            $response = Http::withHeaders([
                'Authorization' => 'JWT ' . $this->token,
                'Content-Type'  => 'application/json',
            ])->post("{$this->baseUrl}/iclock/api/transactions/", $payload);

            $result = $response->json();

            // --- DETECCIÓN DE TOKEN EXPIRADO ---
            // Si el status es 401 o el mensaje dice "expired"
            if ($response->status() === 401 || (isset($result['detail']) && str_contains(strtolower($result['detail']), 'expired'))) {
                if (!$isRetry) {
                    Log::warning("Firma expirada detectada para {$data['emp_code']}. Renovando token...");
                    
                    $this->login(true); // Forzamos un nuevo login para obtener un token fresco
                    
                    if ($this->token) {
                        // Reintentamos la operación una única vez con el nuevo token
                        return $this->crearChecada($data, true);
                    }
                }
            }

            if ($response->successful() || $response->status() === 201) {
                return ['success' => true, 'data' => $result];
            }

            return [
                'success' => false, 
                'error'   => $result ?? $response->body(),
                'status'  => $response->status()
            ];

        } catch (Exception $e) {
            Log::error("Error crítico inyectando checada: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}