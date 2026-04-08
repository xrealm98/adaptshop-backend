<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar si el usuario existe y si tiene el rol de 'admin'
        if (!$request->user() || $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acceso restringido para administradores'], 403);
        }
        return $next($request);
    }
}
