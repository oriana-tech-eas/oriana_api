<?php

namespace Database\Seeders;

use App\Models\Contacts;
use Illuminate\Database\Seeder;

class ContactsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Contacts::factory(10)->create();
    }
}
