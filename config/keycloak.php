<?php

return [
    'realm' => env('KEYCLOAK_REALM', 'oriana'),
    'server_url' => env('KEYCLOAK_SERVER_URL', 'https://auth.oriana.orb.local'),
    'client_id' => env('KEYCLOAK_CLIENT_ID', 'oriana-api'),
    'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
    
    // Public key for JWT verification - you can get this from Keycloak
    // GET https://auth.oriana.orb.local/realms/oriana-services/protocol/openid_connect/certs
    'public_key' => env('KEYCLOAK_PUBLIC_KEY'),
    
    // Alternative: Use Keycloak's public key endpoint (recommended)
    'public_key_endpoint' => env('KEYCLOAK_PUBLIC_KEY_ENDPOINT'),
    
    // Cache settings for public keys
    'cache_public_keys' => env('KEYCLOAK_CACHE_PUBLIC_KEYS', true),
    'cache_ttl' => env('KEYCLOAK_CACHE_TTL', 3600), // 1 hour
];
