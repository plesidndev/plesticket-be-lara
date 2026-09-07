<?php

namespace Database\Seeders;

use App\Models\CatalogTimezone;
use DateTimeZone;
use Illuminate\Database\Seeder;

class CatalogTimezoneSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [];
        foreach (DateTimeZone::listIdentifiers(DateTimeZone::ALL) as $code) {
            $rows[] = [
                'code' => $code, 'name' => str_replace(['_', '/'], [' ', ' / '], $code),
                'is_active' => true, 'sort_order' => $code === 'UTC' ? 0 : 100,
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        // Refresh missing identifiers without overwriting admin customizations.
        foreach (array_chunk($rows, 100) as $chunk) {
            CatalogTimezone::insertOrIgnore($chunk);
        }
    }
}
