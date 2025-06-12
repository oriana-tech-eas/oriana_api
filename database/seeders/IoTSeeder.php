<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\IoT\Device;
use App\Models\User;
use Illuminate\Support\Str;

class IoTSeeder extends Seeder
{
    public function run()
    {

        // Create test customers
        $customer1 = Customer::firstOrCreate(
            ['email' => 'empresa@test.com'],
            [
                'name' => 'Empresa Test SA',
                'keycloak_user_id' => 'test-keycloak-id-1',
                'subscription_tier' => 'professional',
                'max_devices' => 10,
                'status' => 'active',
            ]
        );

        $customer2 = Customer::firstOrCreate(
            ['email' => 'startup@test.com'],
            [
                'name' => 'Startup Innovadora',
                'keycloak_user_id' => 'test-keycloak-id-2',
                'subscription_tier' => 'starter',
                'max_devices' => 5,
                'status' => 'trial',
            ]
        );

        // Create test devices for customer 1
        $networkDevice = Device::firstOrCreate(
            ['device_id' => 'pi-network-001'],
            [
                'customer_id' => $customer1->id,
                'device_type' => 'network',
                'name' => 'Oficina Principal - Red',
                'status' => 'active',
                'api_key' => 'device_' . Str::random(40),
                'last_seen' => now()->subMinutes(5),
                'metadata' => [
                    'hardware' => 'Raspberry Pi 4',
                    'location' => 'Oficina Principal',
                    'ip_address' => '192.168.1.100',
                ],
            ]
        );

        $serverDevice = Device::firstOrCreate(
            ['device_id' => 'server-ubuntu-001'],
            [
                'customer_id' => $customer1->id,
                'device_type' => 'server',
                'name' => 'Servidor de Archivos',
                'status' => 'active',
                'api_key' => 'device_' . Str::random(40),
                'last_seen' => now()->subMinutes(2),
                'metadata' => [
                    'hardware' => 'Ubuntu Server 22.04',
                    'location' => 'Sala de Servidores',
                    'ip_address' => '192.168.1.10',
                ],
            ]
        );

        // Create test devices for customer 2
        $hybridDevice = Device::firstOrCreate(
            ['device_id' => 'hybrid-mini-001'],
            [
                'customer_id' => $customer2->id,
                'device_type' => 'hybrid',
                'name' => 'Todo en Uno',
                'status' => 'active',
                'api_key' => 'device_' . Str::random(40),
                'last_seen' => now()->subMinutes(1),
                'metadata' => [
                    'hardware' => 'Intel NUC',
                    'location' => 'Oficina Startup',
                    'ip_address' => '10.0.0.50',
                ],
            ]
        );

        $this->command->info('IoT test data created successfully!');
        $this->command->info("Customer 1: {$customer1->name} - Devices: {$customer1->devices()->count()}");
        $this->command->info("Customer 2: {$customer2->name} - Devices: {$customer2->devices()->count()}");
        $this->command->info("Network Device API Key: {$networkDevice->api_key}");
        $this->command->info("Server Device API Key: {$serverDevice->api_key}");
    }
}
