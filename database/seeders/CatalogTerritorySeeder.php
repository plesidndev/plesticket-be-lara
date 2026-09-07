<?php

namespace Database\Seeders;

use App\Models\CatalogTerritory;
use App\Services\Catalog\CatalogTerritoryCodes;
use Illuminate\Database\Seeder;

class CatalogTerritorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [];
        foreach (CatalogTerritoryCodes::entries() as $code => $name) {
            $rows[] = [
                'code' => $code, 'name' => $name, 'is_active' => true,
                'sort_order' => $code === 'WORLD' ? 0 : 100,
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        // Preserve names, ordering, and activation settings maintained by admins.
        CatalogTerritory::insertOrIgnore($rows);
    }
}
