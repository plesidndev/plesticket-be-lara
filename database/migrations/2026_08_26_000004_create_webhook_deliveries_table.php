<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider', 30);
            $table->string('event_type', 60)->nullable();

            // Nullable: a payload we could not parse still gets recorded.
            $table->string('reference_id', 64)->nullable();

            $table->string('status', 20);
            $table->json('payload');
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // Redeliveries intentionally create separate rows — this is an
            // audit log, not a deduplication key.
            $table->index(['provider', 'status']);
            $table->index('reference_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
