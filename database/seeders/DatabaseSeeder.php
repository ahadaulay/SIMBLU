<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            UnitSeeder::class,
            UnitAccountSeeder::class,
            // Reporting2026Seeder::class,
            CompleteWorkbookSeeder::class,
        ]);
    }
}
