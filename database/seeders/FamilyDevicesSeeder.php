<?php
// database/seeders/FamilyDevicesSeeder.php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\IoT\DeviceProfile;
use App\Models\IoT\FamilyDevice;
use App\Models\IoT\DeviceSession;
use App\Models\IoT\DeviceTrafficLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FamilyDevicesSeeder extends Seeder
{
    public function run()
    {
        // Get the first customer (or create one for testing)
        $customer = Customer::first();
        
        if (!$customer) {
            $customer = Customer::create([
                'id' => Str::uuid(),
                'name' => 'Test Customer',
                'email' => 'test@example.com',
                'subscription_tier' => 'professional',
                'max_devices' => 10,
                'status' => 'active'
            ]);
        }

        // Create device profiles
        $parentProfile = DeviceProfile::create([
            'customer_id' => $customer->id,
            'name' => 'Perfil de Padre',
            'description' => 'Acceso completo con restricciones mínimas',
            'is_default' => false
        ]);

        $kidsProfile = DeviceProfile::create([
            'customer_id' => $customer->id,
            'name' => 'Perfil de Niños',
            'description' => 'Acceso limitado con control parental',
            'is_default' => false
        ]);

        $defaultProfile = DeviceProfile::create([
            'customer_id' => $customer->id,
            'name' => 'Sin Restricciones',
            'description' => 'Acceso completo sin filtros',
            'is_default' => true
        ]);

        // Create family devices
        $anaPhone = FamilyDevice::create([
            'customer_id' => $customer->id,
            'name' => 'iPhone de Ana',
            'avatar' => 'penguin',
            'mac_address' => '00:1A:2B:3C:4D:5E',
            'device_model' => 'iPhone 14 Pro',
            'device_type' => 'mobile',
            'manufacturer' => 'Apple',
            'current_ip' => '192.168.1.101',
            'is_identified' => true,
            'profile_id' => $parentProfile->id,
            'last_seen' => now(),
            'connection_started_at' => now()->subHours(2)
        ]);

        $carlosLaptop = FamilyDevice::create([
            'customer_id' => $customer->id,
            'name' => 'Laptop de Carlos',
            'avatar' => 'koala',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'device_model' => 'MacBook Pro',
            'device_type' => 'laptop',
            'manufacturer' => 'Apple',
            'current_ip' => '192.168.1.102',
            'is_identified' => true,
            'profile_id' => $parentProfile->id,
            'last_seen' => now(),
            'connection_started_at' => now()->subHour()
        ]);

        $kidsIpad = FamilyDevice::create([
            'customer_id' => $customer->id,
            'name' => 'iPad de los Niños',
            'avatar' => 'elephant',
            'mac_address' => '11:22:33:44:55:66',
            'device_model' => 'iPad Air',
            'device_type' => 'tablet',
            'manufacturer' => 'Apple',
            'current_ip' => '192.168.1.103',
            'is_identified' => true,
            'profile_id' => $kidsProfile->id,
            'last_seen' => now(),
            'connection_started_at' => now()->subMinutes(30)
        ]);

        // Create active sessions
        $anaSession = DeviceSession::create([
            'family_device_id' => $anaPhone->id,
            'started_at' => now()->subHours(2),
            'data_usage_bytes' => 1610612736, // 1.5 GB
            'is_active' => true
        ]);

        $carlosSession = DeviceSession::create([
            'family_device_id' => $carlosLaptop->id,
            'started_at' => now()->subHour(),
            'data_usage_bytes' => 536870912, // 512 MB
            'is_active' => true
        ]);

        $kidsSession = DeviceSession::create([
            'family_device_id' => $kidsIpad->id,
            'started_at' => now()->subMinutes(30),
            'data_usage_bytes' => 268435456, // 256 MB
            'is_active' => true
        ]);

        // Create some traffic logs
        DeviceTrafficLog::create([
            'family_device_id' => $anaPhone->id,
            'recorded_at' => now()->subHour(),
            'bytes_downloaded' => 1073741824, // 1GB
            'bytes_uploaded' => 536870912,    // 512MB
            'session_id' => $anaSession->id
        ]);

        DeviceTrafficLog::create([
            'family_device_id' => $carlosLaptop->id,
            'recorded_at' => now()->subMinutes(30),
            'bytes_downloaded' => 268435456,  // 256MB
            'bytes_uploaded' => 134217728,    // 128MB
            'session_id' => $carlosSession->id
        ]);

        DeviceTrafficLog::create([
            'family_device_id' => $kidsIpad->id,
            'recorded_at' => now()->subMinutes(15),
            'bytes_downloaded' => 134217728,  // 128MB
            'bytes_uploaded' => 67108864,     // 64MB
            'session_id' => $kidsSession->id
        ]);

        $this->command->info('✅ Family devices test data created successfully!');
        $this->command->info("📱 Customer: {$customer->name} ({$customer->id})");
        $this->command->info("📊 Created {$customer->familyDevices()->count()} family devices");
        $this->command->info("👥 Created {$customer->deviceProfiles()->count()} device profiles");
    }
}