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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $role  El rol requerido (ej. 'admin')
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!Auth::check()) {
            return redirect('login');
        }

        $user = Auth::user();

        // Si el usuario es admin, tiene acceso a todo (opcional) o validamos estricto
        if ($user->role === 'admin') {
            return $next($request);
        }

        // Si el rol requerido es el del usuario, pasa
        if ($user->role === $role) {
            return $next($request);
        }

        // Si no cumple, abortamos con error 403 (Prohibido)
        abort(403, 'No tienes permisos para acceder a esta sección.');
    }
}