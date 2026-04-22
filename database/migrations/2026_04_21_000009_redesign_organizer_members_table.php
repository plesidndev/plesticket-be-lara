<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('organizer_members');

        Schema::create('organizer_members', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 40)->unique()->nullable();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('event_id')->index();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->string('name');
            $table->string('email', 100)->nullable();
            $table->string('password');
            $table->string('role', 50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_members');
    }
};
