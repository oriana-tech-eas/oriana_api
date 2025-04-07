<?php

namespace Database\Seeders;

use App\Models\Plans;
use Illuminate\Database\Seeder;

class PlansTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Printit Free',
                'description' => 'Printit flee plan',
            ],
            [
                'name' => 'Printit Pro',
                'description' => 'Printit pro plan',
            ],
            [
                'name' => 'Printit Enterprise',
                'description' => 'Printit enterprise plan',
            ],
        ];

        Plans::factory()->createMany($plans);
    }
}
