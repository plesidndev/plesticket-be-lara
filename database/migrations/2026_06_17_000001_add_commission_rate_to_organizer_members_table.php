<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizer_members', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->default(0)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('organizer_members', function (Blueprint $table) {
            $table->dropColumn('commission_rate');
        });
    }
};
