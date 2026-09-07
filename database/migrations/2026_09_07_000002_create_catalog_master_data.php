<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['catalog_genres', 'catalog_languages'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->string('code', 40)->primary();
                $table->string('name', 100)->unique();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_languages');
        Schema::dropIfExists('catalog_genres');
    }
};
