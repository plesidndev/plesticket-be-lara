<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SuperAdminSeeder::class,
            BankSeeder::class,
            CategorySeeder::class,
            ProvinceSeeder::class,
            CitySeeder::class,
        ]);
    }
}
