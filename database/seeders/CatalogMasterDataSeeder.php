<?php

namespace Database\Seeders;

use App\Models\CatalogGenre;
use App\Models\CatalogLanguage;
use Illuminate\Database\Seeder;

class CatalogMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogTerritorySeeder::class);
        $this->call(CatalogTimezoneSeeder::class);
        // Starter vocabulary for PlesConnect, not a claimed copy of a partner's taxonomy.
        $genres = [
            'pop' => 'Pop', 'rock' => 'Rock', 'alternative' => 'Alternative', 'indie' => 'Indie',
            'hip-hop-rap' => 'Hip-Hop/Rap', 'rnb-soul' => 'R&B/Soul', 'electronic' => 'Electronic',
            'dance' => 'Dance', 'jazz' => 'Jazz', 'blues' => 'Blues', 'classical' => 'Classical',
            'country' => 'Country', 'folk' => 'Folk', 'reggae' => 'Reggae', 'metal' => 'Metal',
            'punk' => 'Punk', 'hardcore' => 'Hardcore', 'dangdut' => 'Dangdut', 'world' => 'World',
            'african' => 'African', 'acid-punk' => 'Acid punk', 'soundtrack' => 'Soundtrack',
        ];
        $languages = [
            'id' => 'Indonesian', 'en' => 'English', 'jv' => 'Javanese', 'su' => 'Sundanese',
            'ms' => 'Malay', 'ar' => 'Arabic', 'zh' => 'Chinese', 'ja' => 'Japanese',
            'ko' => 'Korean', 'hi' => 'Hindi', 'es' => 'Spanish', 'fr' => 'French',
            'de' => 'German', 'it' => 'Italian', 'pt' => 'Portuguese', 'th' => 'Thai',
        ];
        foreach ([CatalogGenre::class => $genres, CatalogLanguage::class => $languages] as $model => $entries) {
            foreach ($entries as $code => $name) {
                $model::firstOrCreate(['code' => $code], ['name' => $name]);
            }
        }
    }
}
