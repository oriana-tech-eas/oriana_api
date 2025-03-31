<?php

namespace App\Http\Middleware;

use App\Models\CompanyUser;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CompanyRequiredMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = User::find(Auth::id());

        // Si es super-admin, permitir acceso sin verificar empresas
        if ($user && $user->hasAnyRole(['super-admin'])) {
            return $next($request);
        }

        // Verificar si el usuario tiene al menos una empresa
        $hasCompany = CompanyUser::where('user_id', $user->id)->exists();

        if (! $hasCompany) {
            return response()->json([
                'error' => 'Debes tener al menos una empresa para acceder a esta funcionalidad',
            ], 403);
        }

        return $next($request);
    }
}
