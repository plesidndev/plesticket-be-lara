<?php

namespace Database\Seeders;

use App\Models\CatalogDsp;
use Illuminate\Database\Seeder;

class CatalogDspSeeder extends Seeder
{
    public function run(): void
    {
        // Preserve the existing destinations and admin customizations on reseed.
        $groups = [
            'music' => ['spotify' => 'Spotify', 'apple_music' => 'Apple Music', 'youtube_music' => 'YouTube Music', 'amazon_music' => 'Amazon Music', 'deezer' => 'Deezer', 'tidal' => 'Tidal', 'tiktok' => 'TikTok'],
            'music_video' => ['apple_music_video' => 'Apple Music Video', 'tidal_video' => 'Tidal Video', 'boomplay_video' => 'Boomplay Video'],
        ];
        foreach ($groups as $type => $entries) {
            $order = 0;
            foreach ($entries as $code => $name) {
                CatalogDsp::firstOrCreate(['code' => $code], ['name' => $name, 'supported_types' => [$type], 'sort_order' => $order++]);
            }
        }
    }
}
