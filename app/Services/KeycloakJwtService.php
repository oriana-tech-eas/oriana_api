<?php

namespace App\Services;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KeycloakJwtService
{
    private $config;
    
    public function __construct()
    {
        $this->config = config('keycloak');
    }
    
    public function validateToken(string $token): array
    {
        $publicKey = $this->getPublicKey();
        
        try {
            $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));
            return (array) $decoded;
        } catch (Exception $e) {
            throw new Exception('Invalid JWT token: ' . $e->getMessage());
        }
    }
    
    public function extractCustomerInfo(array $tokenPayload): array
    {
        return [
            'keycloak_user_id' => $tokenPayload['sub'] ?? null,
            'email' => $tokenPayload['email'] ?? null,
            'name' => $tokenPayload['name'] ?? null,
            'customer_id' => $tokenPayload['customer_id'] ?? null, // Custom attribute
            'customer_tier' => $tokenPayload['customer_tier'] ?? null,
            'max_devices' => $tokenPayload['max_devices'] ?? null,
            'roles' => $tokenPayload['realm_access']['roles'] ?? [],
        ];
    }
    
    private function getPublicKey(): string
    {
        $cacheKey = 'keycloak_public_key';
        
        if ($this->config['cache_public_keys'] && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        // Option 1: Use static public key from config
        if (!empty($this->config['public_key'])) {
            $publicKey = $this->formatPublicKey($this->config['public_key']);
            
            if ($this->config['cache_public_keys']) {
                Cache::put($cacheKey, $publicKey, $this->config['cache_ttl']);
            }
            
            return $publicKey;
        }
        
        // Option 2: Fetch from Keycloak endpoint (recommended)
        if (!empty($this->config['public_key_endpoint'])) {
            $publicKey = $this->fetchPublicKeyFromKeycloak();
            
            if ($this->config['cache_public_keys']) {
                Cache::put($cacheKey, $publicKey, $this->config['cache_ttl']);
            }
            
            return $publicKey;
        }
        
        throw new Exception('No public key configuration found');
    }
    
    private function fetchPublicKeyFromKeycloak(): string
    {
        try {
            $response = Http::timeout(10)->get($this->config['public_key_endpoint']);
            
            if (!$response->successful()) {
                throw new Exception('Failed to fetch public key from Keycloak');
            }
            
            $keys = $response->json('keys');
            
            if (empty($keys)) {
                throw new Exception('No keys found in Keycloak response');
            }
            
            // Get the first key (or implement logic to select the right one)
            $keyData = $keys[0];
            
            // Convert JWK to PEM format
            return $this->jwkToPem($keyData);
            
        } catch (Exception $e) {
            Log::error('Failed to fetch Keycloak public key', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    private function jwkToPem(array $jwk): string
    {
        // This is a simplified version - you might want to use a proper JWK to PEM library
        // For now, assuming the key is already in a usable format
        
        if (isset($jwk['x5c']) && !empty($jwk['x5c'])) {
            $cert = $jwk['x5c'][0];
            return "-----BEGIN CERTIFICATE-----\n" .
                   chunk_split($cert, 64, "\n") .
                   "-----END CERTIFICATE-----\n";
        }
        
        throw new Exception('Unable to convert JWK to PEM format');
    }
    
    private function formatPublicKey(string $key): string
    {
        // Ensure proper PEM format
        if (strpos($key, '-----BEGIN') === false) {
            return "-----BEGIN PUBLIC KEY-----\n" .
                   chunk_split($key, 64, "\n") .
                   "-----END PUBLIC KEY-----\n";
        }
        
        return $key;
    }
}
