<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_dsps', function (Blueprint $table) {
            $table->string('code', 40)->primary();
            $table->string('name', 100)->unique();
            $table->json('supported_types');
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_dsps');
    }
};
