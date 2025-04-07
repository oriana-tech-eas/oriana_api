<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): Response
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
    
        $user = User::where('email', $request->email)->first();
    
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return new Response(response()->json([
                'message' => 'The provided credentials are incorrect.'
            ], 401)->getContent(), 401, ['Content-Type' => 'application/json']);
        }
    
        // Generate token for API
        $token = $user->createToken('auth-token')->plainTextToken;
    
        return new Response(response()->json([
            'token' => $token,
            'user' => $user
        ])->getContent(), 200, ['Content-Type' => 'application/json']);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();
    
        return new Response(response()->json([
            'message' => 'Logged out successfully'
        ]), 200, ['Content-Type' => 'application/json']);
    }
}
