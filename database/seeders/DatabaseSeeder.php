<?php
// database/seeders/DatabaseSeeder.php
// Main seeder that calls DemoDataSeeder for DriftWatch demo data.

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(UserSeeder::class);
        $this->call(DemoDataSeeder::class);
    }
}
