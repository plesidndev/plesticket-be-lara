<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('province_code', 5);
            $table->string('name', 150);
            $table->enum('type', ['KABUPATEN', 'KOTA']);

            $table->foreign('province_code')->references('code')->on('provinces');
            $table->index('province_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};