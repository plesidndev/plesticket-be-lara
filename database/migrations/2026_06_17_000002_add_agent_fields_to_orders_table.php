<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->after('buyer_id')
                ->constrained('organizer_members')->nullOnDelete();
            $table->boolean('is_agent_sale')->default(false)->after('agent_id');
            $table->string('buyer_name', 255)->nullable()->after('is_agent_sale');
            $table->string('buyer_phone', 30)->nullable()->after('buyer_name');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_id');
            $table->dropColumn(['is_agent_sale', 'buyer_name', 'buyer_phone']);
        });
    }
};
