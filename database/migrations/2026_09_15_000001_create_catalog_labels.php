<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record labels an admin curates so the catalog form can suggest them. Unlike genres or
     * languages this list never constrains a release: metadata.label stays free text, because
     * an artist releasing under their own imprint must still be able to type it.
     */
    public function up(): void
    {
        Schema::create('catalog_labels', function (Blueprint $table) {
            $table->string('code', 40)->primary();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_labels');
    }
};
