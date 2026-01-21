<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles  // <--- Aceptamos lista de roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!Auth::check()) {
            return redirect('login');
        }

        $user = Auth::user();

        // 1. Si el usuario es 'admin', le damos pase VIP a todo
        if ($user->role === 'admin') {
            return $next($request);
        }

        // 2. Si el rol del usuario está en la lista de roles permitidos, pasa
        // (Ejemplo: Si la ruta dice 'role:supervisor' y el usuario es 'supervisor')
        if (in_array($user->role, $roles)) {
            return $next($request);
        }

        // Si no tiene el rol necesario, denegamos acceso
        abort(403, 'No tienes permisos para acceder a esta sección.');
    }
}