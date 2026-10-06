<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Model events assign unique operational codes to seeded accounts.

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            JassCatalogSeeder::class,
            JassAccessSeeder::class,
        ]);
    }
}
