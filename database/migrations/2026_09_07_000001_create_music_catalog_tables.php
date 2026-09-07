<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_releases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->string('title');
            $table->json('metadata');
            $table->string('status', 40)->default('draft')->index();
            $table->unsignedInteger('version')->default(0);
            $table->boolean('has_change_request')->default(false)->index();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('distribution')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'type']);
        });

        Schema::create('catalog_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('release_id')->constrained('catalog_releases')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->uuid('track_id')->nullable();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->boolean('is_current')->default(true);
            $table->timestamps();
        });

        Schema::create('catalog_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('release_id')->constrained('catalog_releases')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->timestamp('created_at');
            $table->unique(['release_id', 'version']);
        });

        Schema::create('catalog_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('release_id')->constrained('catalog_releases')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->unsignedInteger('version');
            $table->text('message')->nullable();
            $table->json('fields')->nullable();
            $table->boolean('internal')->default(false);
            $table->json('context')->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('catalog_store_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('release_id')->constrained('catalog_releases')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('store', 60);
            $table->string('status', 30)->default('pending');
            $table->string('url', 2048)->nullable();
            $table->timestamp('live_at')->nullable();
            $table->timestamps();
            $table->unique(['release_id', 'version', 'store']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_store_deliveries');
        Schema::dropIfExists('catalog_activities');
        Schema::dropIfExists('catalog_submissions');
        Schema::dropIfExists('catalog_assets');
        Schema::dropIfExists('catalog_releases');
    }
};
