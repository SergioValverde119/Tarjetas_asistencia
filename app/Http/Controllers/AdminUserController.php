<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Repositories\TarjetaRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Exception;

/**
 * Controlador para la administración de usuarios del sistema.
 * * Características:
 * - Estandarización a minúsculas de identidades y accesos.
 * - Validación flexible (Nombres y Apellidos opcionales).
 * - Búsqueda atómica en BioTime vía SQL directo.
 * - Registro de auditoría implícito vía modelos.
 * * Primeramente Jehová Dios y Jesús Rey.
 */
class AdminUserController extends Controller
{
    protected $repository;

    /**
     * Constructor con inyección de repositorio para compatibilidad.
     */
    public function __construct(TarjetaRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Muestra el listado maestro de usuarios.
     * Utiliza el scope 'buscar' definido en el modelo User.
     */
    public function index(Request $request)
    {
        $users = User::buscar($request->search)
            ->orderBy('role', 'asc')
            ->orderBy('username', 'asc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('User/Index', [
            'users' => $users,
            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * Muestra el formulario de creación.
     */
    public function create()
    {
        return Inertia::render('User/Create');
    }

    /**
     * Comprueba la existencia de un empleado en BioTime de forma eficiente.
     * Realiza una consulta SQL directa para evitar carga masiva en memoria.
     */
    public function checkBiotime(Request $request)
    {
        $request->validate([
            'emp_code' => 'nullable|string',
            'name'     => 'nullable|string'
        ]);

        try {
            // Conexión directa a BioTime para máxima velocidad
            $query = DB::connection('pgsql_biotime')
                ->table('personnel_employee')
                ->select('id', 'emp_code', 'first_name', 'last_name')
                ->where('status', 0); // Solo empleados activos

            $matchType = '';
            $emp = null;

            // Prioridad 1: Búsqueda por número de nómina/código
            if ($request->filled('emp_code')) {
                $emp = (clone $query)->where('emp_code', (string)$request->emp_code)->first();
                $matchType = 'code';
            } 
            
            // Prioridad 2: Búsqueda por nombre (si no se halló por código)
            if (!$emp && $request->filled('name')) {
                $term = strtolower(trim($request->name));
                $emp = (clone $query)->whereRaw("LOWER(first_name || ' ' || last_name) LIKE ?", ["%$term%"])->first();
                $matchType = 'name';
            }

            if ($emp) {
                return response()->json([
                    'status' => 'success',
                    'match_type' => $matchType,
                    'biotime_id' => $emp->id,
                    'emp_code_confirmed' => $emp->emp_code,
                    'employee' => $emp
                ]);
            }

            return response()->json([
                'status' => 'error', 
                'message' => 'No se localizó al empleado en los registros de BioTime.'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error', 
                'message' => 'Fallo de conexión con el servidor de asistencia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registra un nuevo usuario aplicando normalización preventiva.
     */
    public function store(Request $request)
    {
        // --- NORMALIZACIÓN A MINÚSCULAS ---
        // Se ejecuta antes de validar para que el chequeo de 'unique' sea exacto.
        $request->merge([
            'username' => strtolower(trim($request->username)),
            'nombre'   => $request->nombre ? strtolower(trim($request->nombre)) : null,
            'paterno'  => $request->paterno ? strtolower(trim($request->paterno)) : null,
            'materno'  => $request->materno ? strtolower(trim($request->materno)) : null,
            'rfc'      => $request->rfc ? strtolower(trim($request->rfc)) : null,
            'curp'     => $request->curp ? strtolower(trim($request->curp)) : null,
            'email'    => $request->email ? strtolower(trim($request->email)) : null,
        ]);

        $v = $request->validate([
            // Identidad (Flexibilizados a opcionales)
            'nombre'     => 'nullable|string|max:100',
            'paterno'    => 'nullable|string|max:100',
            'materno'    => 'nullable|string|max:100',
            'rfc'        => 'nullable|string|max:13|unique:users,rfc',
            'curp'       => 'nullable|string|max:18|unique:users,curp',
            
            // Cuenta de Acceso
            'username'   => 'required|string|max:20|unique:users,username', 
            'email'      => 'nullable|email|max:255|unique:users,email',
            'password'   => ['required', 'confirmed', 'min:6'], 
            'role'       => 'required|in:admin,empleado,disponibilidad,capturista,asistencia,supervisor',
            
            // Vinculación BioTime
            'biotime_id' => 'nullable|integer', 
            'emp_code'   => 'nullable|string|max:20', 
        ]);

        // Cifrado de contraseña
        $v['password'] = Hash::make($request->password);

        // Si no se proveyó email, generamos uno interno basado en el username
        if (!$request->filled('email')) {
            $v['email'] = $v['username'] . '@sistema.local';
        }

        // Creación del registro (El modelo User se encarga de la consistencia final)
        User::create($v);

        return redirect()->route('users.index')->with('success', 'usuario registrado correctamente bajo la gracia de Dios.');
    }

    /**
     * Muestra el formulario de edición.
     */
    public function edit(User $user)
    {
        return Inertia::render('User/Edit', [
            'user' => $user
        ]);
    }

    /**
     * Actualiza la información de un usuario existente.
     */
    public function update(Request $request, User $user)
    {
        // Normalización preventiva
        $request->merge([
            'username' => strtolower(trim($request->username)),
            'nombre'   => $request->nombre ? strtolower(trim($request->nombre)) : null,
            'paterno'  => $request->paterno ? strtolower(trim($request->paterno)) : null,
            'materno'  => $request->materno ? strtolower(trim($request->materno)) : null,
            'rfc'      => $request->rfc ? strtolower(trim($request->rfc)) : null,
            'curp'     => $request->curp ? strtolower(trim($request->curp)) : null,
            'email'    => $request->email ? strtolower(trim($request->email)) : null,
        ]);

        $v = $request->validate([
            'nombre'     => 'nullable|string|max:100',
            'paterno'    => 'nullable|string|max:100',
            'materno'    => 'nullable|string|max:100',
            'rfc'        => ['nullable', 'string', 'max:13', Rule::unique('users')->ignore($user->id)],
            'curp'       => ['nullable', 'string', 'max:18', Rule::unique('users')->ignore($user->id)],
            'username'   => ['required', 'string', 'max:20', Rule::unique('users')->ignore($user->id)],
            'email'      => ['nullable', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role'       => 'required|in:admin,empleado,disponibilidad,capturista,asistencia,supervisor',
            'biotime_id' => 'nullable|integer',
            'emp_code'   => 'nullable|string|max:20',
            'password'   => ['nullable', 'confirmed', 'min:6'], 
        ]);

        // Procesamiento de contraseña (solo si se envió una nueva)
        if ($request->filled('password')) {
            $v['password'] = Hash::make($request->password);
        } else {
            unset($v['password']);
        }

        $user->update($v);

        return redirect()->route('users.index')->with('success', 'usuario actualizado con éxito.');
    }

    /**
     * Elimina el acceso de un usuario.
     * Incluye protección para evitar el auto-borrado del administrador activo.
     */
    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'operación denegada: no puedes eliminar tu propia cuenta de acceso.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'el usuario ha sido removido del sistema.');
    }
}