<?php

namespace App\Services\Catalog;

class CatalogTerritoryCodes
{
    public static function entries(): array
    {
        $data = json_decode(file_get_contents(database_path('data/iso3166-1.json')), true, 512, JSON_THROW_ON_ERROR);
        $entries = ['WORLD' => 'Worldwide'];
        foreach ($data['3166-1'] as $entry) {
            $entries[$entry['alpha_2']] = $entry['name'];
        }

        return $entries;
    }
}
