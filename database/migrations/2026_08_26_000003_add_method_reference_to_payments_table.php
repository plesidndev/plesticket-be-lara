<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Xendit's Payment Requests API splits identity in two: webhooks
            // quote the payment request (pr-...), while expiring a charge needs
            // the payment method (pm-...). provider_reference holds the former.
            $table->string('provider_method_reference', 100)
                ->nullable()
                ->after('provider_reference');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('provider_method_reference');
        });
    }
};
