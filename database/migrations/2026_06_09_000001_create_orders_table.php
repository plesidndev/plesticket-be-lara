<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number', 20)->unique();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('event_id');
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->string('status', 20)->default('pending_payment');
            $table->decimal('total_price', 12, 2);
            $table->string('payment_method', 50)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
