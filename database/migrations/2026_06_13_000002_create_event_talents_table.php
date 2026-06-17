<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_talents', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id');
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreignId('talent_id')->nullable()->constrained('talents')->nullOnDelete();
            $table->string('free_name')->nullable(); // talent not yet in masterdata
            $table->string('role')->default('performer'); // headliner, opening_act, dj, mc, special_guest, performer
            $table->unsignedTinyInteger('performance_order')->default(0);
            $table->string('performance_time')->nullable(); // e.g. "20:00 – 21:30"
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_talents');
    }
};
