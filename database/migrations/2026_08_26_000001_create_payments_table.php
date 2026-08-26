<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();

            // What we send the provider as its idempotency key. Unique per
            // attempt, so a retried checkout never collides with a live charge.
            $table->string('reference_id', 64)->unique();

            $table->string('provider', 30);
            $table->string('method_code', 50);
            $table->string('type', 30);
            $table->string('status', 20)->default('pending');
            $table->decimal('amount', 12, 2);

            // Buyer-facing instructions — only one is populated per method.
            $table->string('provider_reference', 100)->nullable();
            $table->text('qr_string')->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('checkout_url')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index('provider_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
