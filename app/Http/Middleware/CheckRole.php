<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $role): Response
    {
        if ($request->user()->hasAnyRole(['admin', 'super-admin'])) {
            return $next($request);
        }

        if (! $request->user() || $request->user()->hasRole($role)) {
            return response()->json([
                'error' => 'Unauthorized',
            ], 403);
        }

        return $next($request);
    }
}
