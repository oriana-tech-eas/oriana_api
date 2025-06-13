<?php

namespace Tests\Feature\Api\IoT;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\IoT\Device;
use App\Services\KeycloakJwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CustomerSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var \App\Models\Customer
     */
    protected $customer1;

    /**
     * @var \App\Models\Customer
     */
    protected $customer2;

    /**
     * @var \App\Models\IoT\Device
     */
    protected $device1;

    /**
     * @var \App\Models\IoT\Device
     */
    protected $device2;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test customers
        $this->customer1 = Customer::create([
            'name' => 'Customer One',
            'email' => 'customer1@test.com',
            'keycloak_user_id' => 'keycloak-user-1',
            'subscription_tier' => 'professional',
            'max_devices' => 10,
            'status' => 'active'
        ]);

        $this->customer2 = Customer::create([
            'name' => 'Customer Two',
            'email' => 'customer2@test.com',
            'keycloak_user_id' => 'keycloak-user-2',
            'subscription_tier' => 'starter',
            'max_devices' => 5,
            'status' => 'active'
        ]);

        // Create devices for each customer
        $this->device1 = Device::create([
            'customer_id' => $this->customer1->id,
            'device_id' => 'device-customer-1',
            'name' => 'Customer 1 Device',
            'type' => 'server',
            'status' => 'active',
            'api_key' => 'device_api_key_1',
            'metadata' => ['location' => 'Office 1']
        ]);

        $this->device2 = Device::create([
            'customer_id' => $this->customer2->id,
            'device_id' => 'device-customer-2',
            'name' => 'Customer 2 Device',
            'type' => 'network',
            'status' => 'active',
            'api_key' => 'device_api_key_2',
            'metadata' => ['location' => 'Office 2']
        ]);
    }

    public function test_customer_can_only_see_their_own_devices()
    {
        // Mock JWT token for customer 1
        $token = $this->createMockJwtToken($this->customer1->keycloak_user_id);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json'
        ])->get('/api/iot/dashboard/devices');

        $response->assertStatus(200);
        
        $devices = $response->json('devices');
        
        // Should only see their own device
        $this->assertCount(1, $devices);
        $this->assertEquals($this->device1->id, $devices[0]['id']);
        $this->assertEquals($this->customer1->name, $response->json('customer.name'));
    }

    public function test_customer_cannot_access_other_customer_device()
    {
        // Customer 1 tries to access Customer 2's device
        $token = $this->createMockJwtToken($this->customer1->keycloak_user_id);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json'
        ])->get("/api/iot/dashboard/devices/{$this->device2->id}");

        $response->assertStatus(404);
        $response->assertJson([
            'error' => 'Device not found or access denied'
        ]);
    }

    public function test_suspended_customer_cannot_access_devices()
    {
        // Suspend customer 1
        $this->customer1->update(['status' => 'suspended']);
        
        $token = $this->createMockJwtToken($this->customer1->keycloak_user_id);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json'
        ])->get('/api/iot/dashboard/devices');

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'Account suspended'
        ]);
    }

    public function test_invalid_token_returns_401()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
            'Accept' => 'application/json'
        ])->get('/api/iot/dashboard/devices');

        $response->assertStatus(401);
    }

    public function test_missing_token_returns_401()
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json'
        ])->get('/api/iot/dashboard/devices');

        $response->assertStatus(401);
    }

    public function test_device_actions_require_ownership()
    {
        // Customer 1 tries to restart Customer 2's device
        $token = $this->createMockJwtToken($this->customer1->keycloak_user_id);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json'
        ])->post("/api/iot/dashboard/devices/{$this->device2->id}/actions", [
            'action' => 'restart'
        ]);

        $response->assertStatus(404);
    }

    public function test_customer_can_perform_actions_on_own_devices()
    {
        $token = $this->createMockJwtToken($this->customer1->keycloak_user_id);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json'
        ])->post("/api/iot/dashboard/devices/{$this->device1->id}/actions", [
            'action' => 'restart'
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /**
     * Create a mock JWT token for testing
     * In a real implementation, you'd use the actual JWT library
     */
    private function createMockJwtToken(string $keycloakUserId): string
    {
        // For testing, we'll mock the JWT service
        $this->mock(KeycloakJwtService::class, function ($mock) use ($keycloakUserId) {
            $mock->shouldReceive('validateToken')
                ->andReturn([
                    'sub' => $keycloakUserId,
                    'email' => 'test@example.com',
                    'name' => 'Test User'
                ]);
                
            $mock->shouldReceive('extractCustomerInfo')
                ->andReturn([
                    'keycloak_user_id' => $keycloakUserId,
                    'email' => 'test@example.com',
                    'name' => 'Test User'
                ]);
        });
        
        return 'mock-jwt-token-' . $keycloakUserId;
    }
}
