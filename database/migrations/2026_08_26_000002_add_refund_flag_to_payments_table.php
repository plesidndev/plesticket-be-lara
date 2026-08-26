<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Set when a charge is paid after its order was already settled by
            // another payment — real money we owe back. Nothing clears this
            // automatically; it is an operations queue.
            $table->boolean('requires_refund')->default(false)->after('paid_at');
            $table->index('requires_refund');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['requires_refund']);
            $table->dropColumn('requires_refund');
        });
    }
};
