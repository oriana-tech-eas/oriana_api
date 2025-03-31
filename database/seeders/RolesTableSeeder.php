<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'super-admin'],
            ['name' => 'admin'],
            ['name' => 'owner'],
            ['name' => 'seller'],
            ['name' => 'manager'],
            ['name' => 'accountant'],
        ];

        Role::factory()->createMany($roles);
    }
}
