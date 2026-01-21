<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Repositories\TarjetaRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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

    /**
     * Lista de usuarios.
     */
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->search) {
            $term = $request->search;
            $query->where(function($q) use ($term) {
                $q->where('name', 'ilike', "%{$term}%")
                  ->orWhere('username', 'ilike', "%{$term}%") 
                  ->orWhere('rfc', 'ilike', "%{$term}%")      
                  ->orWhere('emp_code', 'ilike', "%{$term}%");
            });
        }

        $users = $query->orderBy('role', 'asc')
                       ->orderBy('paterno', 'asc')
                       ->paginate(10)
                       ->withQueryString();

        return Inertia::render('User/Index', [
            'users' => $users,
            'filters' => $request->only(['search'])
        ]);
    }

    public function create()
    {
        return Inertia::render('User/Create');
    }

    public function checkBiotime(Request $request)
    {
        $request->validate(['emp_code' => 'nullable|string', 'name' => 'nullable|string']);
        $inputCode = $request->emp_code;
        $inputName = $request->name;

        try {
            $allEmployees = $this->repository->getAllEmployees();
            if ($inputCode) {
                foreach ($allEmployees as $emp) {
                    if (strval($emp->emp_code) === strval($inputCode)) {
                        return response()->json(['status' => 'success', 'match_type' => 'code', 'biotime_id' => $emp->id, 'emp_code_confirmed' => $emp->emp_code, 'employee' => $emp]);
                    }
                }
            }
            if ($inputName) {
                $searchName = trim($inputName);
                foreach ($allEmployees as $emp) {
                    $fullName = $emp->first_name . ' ' . $emp->last_name;
                    if (mb_stripos($fullName, $searchName) !== false) {
                        return response()->json(['status' => 'success', 'match_type' => 'name', 'biotime_id' => $emp->id, 'emp_code_confirmed' => $emp->emp_code, 'employee' => $emp]);
                    }
                }
            }
            return response()->json(['status' => 'error', 'message' => 'No encontrado']);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Guarda el usuario.
     */
    public function store(Request $request)
    {
        $request->validate([
            // CAMBIO: Nombre y Paterno ahora son obligatorios (required)
            'nombre'  => 'required|string|max:100',
            'paterno' => 'required|string|max:100',
            'materno' => 'nullable|string|max:100', // Materno suele ser opcional
            
            // Datos opcionales
            'rfc'     => 'nullable|string|max:13|unique:users,rfc',
            'curp'    => 'nullable|string|max:18|unique:users,curp',
            'email'   => 'nullable|email|max:255|unique:users,email',
            
            // Obligatorios de sistema
            'username' => 'required|string|max:20|unique:users,username', 
            'password' => ['required', 'confirmed', 'min:1'], 
            'role'     => 'required|in:admin,empleado,disponibilidad',
            
            'biotime_id' => 'nullable|integer', 
            'emp_code'   => 'nullable|string|max:20', 
        ]);

        // Construir nombre completo
        $fullName = trim($request->nombre . ' ' . $request->paterno . ' ' . ($request->materno ?? ''));

        // Email: Usar el provisto o generar uno dummy
        $emailToSave = $request->email ?? ($request->username . '@sistema.local');

        User::create([
            'name' => $fullName, 
            'nombre' => $request->nombre, 
            'paterno' => $request->paterno,
            'materno' => $request->materno,
            'rfc' => $request->rfc ? strtoupper($request->rfc) : null,
            'curp' => $request->curp ? strtoupper($request->curp) : null,
            'username' => $request->username, 
            'email' => $emailToSave, 
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'biotime_id' => $request->biotime_id,
            'emp_code' => $request->emp_code, 
        ]);

        return redirect()->route('users.create')->with('success', 'Usuario registrado correctamente.');
    }

    public function edit(User $user)
    {
        return Inertia::render('User/Edit', [
            'user' => $user
        ]);
    }

    /**
     * Actualiza el usuario.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            // CAMBIO: Nombre y Paterno obligatorios también al editar
            'nombre'  => 'required|string|max:100',
            'paterno' => 'required|string|max:100',
            'materno' => 'nullable|string|max:100',
            
            'rfc'     => ['nullable', 'string', 'max:13', Rule::unique('users')->ignore($user->id)],
            'curp'    => ['nullable', 'string', 'max:18', Rule::unique('users')->ignore($user->id)],
            
            'username' => ['required', 'string', 'max:20', Rule::unique('users')->ignore($user->id)],
            'email'    => ['nullable', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            
            'role'       => 'required|in:admin,empleado',
            'biotime_id' => 'nullable|integer',
            'emp_code'   => 'nullable|string|max:20',
            'password'   => ['nullable', 'confirmed', 'min:1'], 
        ]);

        $fullName = trim($request->nombre . ' ' . $request->paterno . ' ' . ($request->materno ?? ''));
        
        $emailToSave = $request->email ?? ($request->username . '@sistema.local');

        $data = [
            'name' => $fullName,
            'nombre' => $request->nombre,
            'paterno' => $request->paterno,
            'materno' => $request->materno,
            'rfc' => $request->rfc ? strtoupper($request->rfc) : null,
            'curp' => $request->curp ? strtoupper($request->curp) : null,
            'username' => $request->username,
            'email' => $emailToSave,
            'role' => $request->role,
            'biotime_id' => $request->biotime_id,
            'emp_code' => $request->emp_code,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }
        $user->delete();
        return redirect()->route('users.index')->with('success', 'Usuario eliminado.');
    }
}