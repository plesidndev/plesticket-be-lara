<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Null means "use the platform default"; a negotiated rate overrides it.
            $table->decimal('platform_fee_percent', 5, 2)->nullable()->after('verification_status');
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference', 30)->unique();
            $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();

            $table->date('period_start');
            $table->date('period_end');

            // Every amount is stored, not recomputed on read: a rate change must never silently
            // restate what an organizer was already told they would be paid.
            $table->decimal('gross_amount', 14, 2);
            $table->decimal('platform_fee_amount', 14, 2);
            $table->decimal('agent_commission_amount', 14, 2);
            $table->decimal('net_amount', 14, 2);

            $table->string('status', 20)->default('pending');
            $table->string('bank_reference', 100)->nullable();
            $table->text('note')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index(['organizer_id', 'status']);
            $table->index('created_at');
        });

        Schema::create('payout_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('payout_id');
            $table->foreign('payout_id')->references('id')->on('payouts')->cascadeOnDelete();

            // orders.id is a uuid, so foreignId() (an unsigned bigint) cannot reference it.
            $table->uuid('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();

            $table->string('order_number', 40);
            $table->decimal('gross_amount', 14, 2);
            $table->decimal('platform_fee_amount', 14, 2);
            $table->decimal('agent_commission_amount', 14, 2);
            $table->decimal('net_amount', 14, 2);
            $table->timestamps();

            // Declared separately: chaining ->unique() onto a foreignId definition does not apply
            // it. An order must never be paid out twice, and the database is the only place that
            // guarantee holds under concurrent payout runs.
            $table->unique('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_lines');
        Schema::dropIfExists('payouts');
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('platform_fee_percent'));
    }
};
