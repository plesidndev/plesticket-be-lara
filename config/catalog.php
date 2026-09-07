<?php

return [
    'disk' => env('CATALOG_DISK', 'catalog'),
    'max_audio_kb' => (int) env('CATALOG_MAX_AUDIO_KB', 512000),
    'max_video_kb' => (int) env('CATALOG_MAX_VIDEO_KB', 2097152),
    'max_artwork_kb' => 35840,
    'artwork_min_pixels' => 3000,
    // Enable only destinations supported by the team's distribution agreement.
    'music_stores' => ['spotify', 'apple_music', 'youtube_music', 'amazon_music', 'deezer', 'tidal', 'tiktok'],
    'video_stores' => ['apple_music_video', 'tidal_video', 'boomplay_video'],
];
