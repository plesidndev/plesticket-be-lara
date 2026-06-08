<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // event_id: EVT0001 now → {custom_code} once ID format is finalized
            $table->string('event_id', 20)->unique()->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Basic info
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->text('banner_url')->nullable();

            // PIC
            $table->string('pic_name');
            $table->string('pic_identity_type', 20);  // ktp, sim, passport
            $table->text('pic_identity_number');       // encrypted
            $table->text('pic_npwp')->nullable();      // encrypted

            // Schedule
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            // Location
            $table->boolean('is_online')->default(false);
            $table->string('venue_name')->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();

            // Verification
            $table->string('verification_status', 20)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');

            // Visibility
            $table->boolean('show_status')->default(true);
            $table->boolean('is_published')->default(false);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
