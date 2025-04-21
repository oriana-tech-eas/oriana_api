<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class AuthServerService
{
    public $ok = false;
    public $error = null;
    public $token = null;
    protected $baseUrl = 'https://authserver.com/api/';
    protected $clientId = 'your-client-id';
    protected $clientSecret = 'your-client-secret';
    protected $grantType = 'client_credentials';
    protected $tokenUrl = 'https://authserver.com/api/token';

    public function saveCompany($data)
    {
        $response = $this->sendRequest('POST', 'companies', $data);

        // Simulando una respuesta exitosa
        $this->ok = true;
    }

    public function checkPermissions($user, $company)
    {
        // Aquí iría la lógica para verificar los permisos del usuario
        // Por ejemplo, una llamada a una API externa

        // Simulando una respuesta exitosa
        return true;
    }

    private function sendRequest($method, $endpoint, $data = [])
    {
        $this->getToken();

        $response = Http::withHeaders([
            'Authorization Bearer' => $this->token,
            'Accept' => 'application/json',
        ])->$method('https://authserver.com/api/' . $endpoint, $data);

        if ($response->successful()) {
            return $response->json();
        } else {
            $this->error = 'Error: ' . $response->status() . ' - ' . $response->body();
            return null;
        }
    }

    private function getToken()
    {
        $token = Cache::get('authserver_token');

        if ($token) {
            return $token;
        }

        $response = Http::post('https://authserver.com/api/token', [
            'client_id' => 'your-client-id',
            'client_secret' => 'your-client-secret',
            'grant_type' => 'client_credentials',
        ]);

        if ($response->successful()) {
            $this->token = $response->json()['access_token'];

            Cache::put('authserver_token', $this->token, 3600);
        } else {
            $this->error = 'Failed to get token';
        }

        return $this->token;
    }
}
