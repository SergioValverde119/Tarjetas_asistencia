<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Repositories\TarjetaRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Exception;

class AdminUserController extends Controller
{
    protected $repository;

    public function __construct(TarjetaRepository $repository)
    {
        $this->repository = $repository;
    }

    public function create()
    {
        return Inertia::render('RegisterUser');
    }

    /**
     * Verifica si el empleado existe en BioTime.
     * Retorna tanto el ID interno (PK) como el Código Visual.
     */
    public function checkBiotime(Request $request)
    {
        $request->validate([
            'emp_code' => 'nullable|string',
            'name'     => 'nullable|string',
        ]);

        $inputCode = $request->emp_code;
        $inputName = $request->name;

        try {
            $allEmployees = $this->repository->getAllEmployees();
            
            // 1. Búsqueda exacta por Código de Empleado (Visual)
            if ($inputCode) {
                foreach ($allEmployees as $emp) {
                    if (strval($emp->emp_code) === strval($inputCode)) {
                        return response()->json([
                            'status' => 'success',
                            'match_type' => 'code',
                            'biotime_id' => $emp->id,       // ID Interno (FK)
                            'emp_code_confirmed' => $emp->emp_code, // Código Visual confirmado
                            'employee' => $emp
                        ]);
                    }
                }
            }

            // 2. Búsqueda por Nombre (Cruce)
            if ($inputName) {
                $searchName = strtolower(trim($inputName));
                foreach ($allEmployees as $emp) {
                    $fullName = strtolower($emp->first_name . ' ' . $emp->last_name);
                    if (str_contains($fullName, $searchName)) {
                        return response()->json([
                            'status' => 'success',
                            'match_type' => 'name',
                            'biotime_id' => $emp->id,       // ID Interno (FK)
                            'emp_code_confirmed' => $emp->emp_code, // Código Visual confirmado
                            'employee' => $emp
                        ]);
                    }
                }
            }

            return response()->json(['status' => 'error', 'message' => 'No encontrado']);

        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Guarda el usuario con RFC y Datos de BioTime
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:20|unique:users', // RFC es el username
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|in:admin,empleado',
            'biotime_id' => 'nullable|integer', // FK (ID Interno)
            'emp_code' => 'nullable|string|max:20', // Código Visual (para mostrar rápido)
        ]);

        User::create([
            'name' => $request->name,
            'username' => $request->username, // Guardamos RFC aquí
            'email' => $request->username . '@sistema.local', // Email generado dummy (si es requerido por la tabla)
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'biotime_id' => $request->biotime_id,
            'emp_code' => $request->emp_code, // Guardamos el código visual también
        ]);

        return redirect()->route('users.create')->with('success', 'Usuario registrado correctamente.');
    }
}