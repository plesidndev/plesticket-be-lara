<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_accounts', function (Blueprint $table) {
            $table->id();

            // One destination per organizer. Unique so a request can never be ambiguous about
            // where the money goes.
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            $table->string('bank_code', 20);
            $table->string('bank_name', 100);
            $table->string('account_number', 40);
            $table->string('account_holder', 255);
            $table->timestamps();
        });

        Schema::table('payouts', function (Blueprint $table) {
            // Who started it. An organizer-requested payout is the same record as an admin-created
            // one; only the origin differs, so this avoids a second state machine.
            $table->string('source', 20)->default('admin')->after('status');
            $table->timestamp('requested_at')->nullable()->after('source');

            // Snapshotted, not joined: the account may be edited later, and the payout must keep
            // showing where the money was actually sent.
            $table->string('bank_name', 100)->nullable()->after('requested_at');
            $table->string('account_number', 40)->nullable()->after('bank_name');
            $table->string('account_holder', 255)->nullable()->after('account_number');
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn(['source', 'requested_at', 'bank_name', 'account_number', 'account_holder']);
        });

        Schema::dropIfExists('payout_accounts');
    }
};
