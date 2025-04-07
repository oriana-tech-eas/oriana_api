<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['token']);
        
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
        
        // For token-based auth, we would verify the token can be used
        $token = $response->json('token');
        
        $authResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/user');
        
        $authResponse->assertStatus(200);
        $authResponse->assertJson(['email' => 'test@example.com']);
    }
}
