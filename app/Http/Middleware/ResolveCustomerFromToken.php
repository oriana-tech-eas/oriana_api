<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ResolveCustomerFromToken
{
    public function handle(Request $request, Closure $next)
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->errorResponse('Authorization token required', 401);
        }

        $token = substr($authHeader, 7);

        try {
            $publicKey = $this->getKeycloakPublicKey();
            $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));
            
            return $this->processDecodedToken($request, $decoded, $next);

        } catch (\Exception $e) {
            Log::error('JWT validation failed', [
                'error' => $e->getMessage(),
                'token_preview' => substr($token, 0, 20) . '...'
            ]);
            
            return $this->errorResponse('Invalid or expired token', 401);
        }
    }

    private function processDecodedToken(Request $request, $decoded, Closure $next)
    {
        // 🔍 DEBUG: Let's see what's in the token
        Log::info('JWT Token Contents', [
            'full_payload' => json_encode($decoded, JSON_PRETTY_PRINT),
            'sub' => $decoded->sub ?? 'missing',
            'email' => $decoded->email ?? 'missing',
            'name' => $decoded->name ?? 'missing',
            'customer_id' => $decoded->customer_id ?? 'missing',
            'realm_access' => $decoded->realm_access ?? 'missing',
            'resource_access' => $decoded->resource_access ?? 'missing',
            'custom_attributes' => $decoded->custom_attributes ?? 'missing',
        ]);

        $keycloakUserId = $decoded->sub ?? null;
        if (!$keycloakUserId) {
            return $this->errorResponse('Invalid token: missing user ID', 401);
        }

        $customer = Customer::where('keycloak_user_id', $keycloakUserId)->first();
        
        if (!$customer) {
            return $this->errorResponse('Customer not found', 403, [
                'keycloak_user_id' => $keycloakUserId,
                'email' => $decoded->email ?? 'unknown'
            ]);
        }

        if ($customer->status !== 'active') {
            return $this->errorResponse('Account suspended', 403, "Your account status is: {$customer->status}");
        }

        // Add to request using attributes (proper way)
        $request->attributes->add([
            'customer' => $customer,
            'token_payload' => $decoded
        ]);

        Log::info('Customer resolved', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
        ]);

        return $next($request);
    }

    private function getKeycloakPublicKey(): string
    {
        $cacheKey = 'keycloak_public_key';
        
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        // Build endpoint URL
        $endpoint = config('keycloak.public_key_endpoint');
        
        Log::info('Fetching Keycloak public key', ['endpoint' => $endpoint]);
        
        try {
            $response = Http::timeout(10)->get($endpoint);
            
            if (!$response->successful()) {
                Log::error('Keycloak request failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception("HTTP {$response->status()}: Failed to fetch Keycloak public key");
            }
            
            $keys = $response->json('keys');
            if (empty($keys)) {
                throw new \Exception('No keys found in Keycloak response');
            }
            
            $keyData = $keys[0];
            $publicKey = $this->convertToPem($keyData);
            
            Cache::put($cacheKey, $publicKey, 3600);
            Log::info('Keycloak public key cached successfully');
            
            return $publicKey;
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch Keycloak public key', [
                'error' => $e->getMessage(),
                'endpoint' => $endpoint
            ]);
            throw $e;
        }
    }
    
    private function convertToPem(array $jwk): string
    {
        if (isset($jwk['x5c']) && !empty($jwk['x5c'])) {
            $cert = $jwk['x5c'][0];
            return "-----BEGIN CERTIFICATE-----\n" .
                   chunk_split($cert, 64, "\n") .
                   "-----END CERTIFICATE-----\n";
        }
        
        throw new \Exception('Unable to convert JWK to PEM format');
    }

    private function errorResponse(string $error, int $status, $details = null)
    {
        $response = ['error' => $error];
        
        if ($details) {
            if (is_string($details)) {
                $response['message'] = $details;
            } elseif (is_array($details)) {
                $response['debug_info'] = $details;
            }
        }
        
        return response()->json($response, $status);
    }
}
