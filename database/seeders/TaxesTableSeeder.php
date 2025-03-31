<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TaxesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $taxes = [
            ['name' => '5%', 'rate' => 5, 'is_shared' => true],
            ['name' => '10%', 'rate' => 10, 'is_shared' => true],
            ['name' => 'Exento', 'rate' => 0, 'is_shared' => true],
        ];

        foreach ($taxes as $tax) {
            \App\Models\Taxes::create($tax);
        }
    }
}
