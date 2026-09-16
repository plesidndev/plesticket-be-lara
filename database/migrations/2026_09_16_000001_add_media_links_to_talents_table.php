<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A talent's own media, separate from the social handles beside it: one link to a single, and
     * the handful of YouTube videos the act wants shown. The videos are a JSON list rather than a
     * table because they are short, ordered, always read with the talent, and never queried on
     * their own.
     */
    public function up(): void
    {
        Schema::table('talents', function (Blueprint $table) {
            $table->string('single_url', 200)->nullable()->after('spotify');
            $table->json('youtube_videos')->nullable()->after('single_url');
        });
    }

    public function down(): void
    {
        Schema::table('talents', function (Blueprint $table) {
            $table->dropColumn(['single_url', 'youtube_videos']);
        });
    }
};
