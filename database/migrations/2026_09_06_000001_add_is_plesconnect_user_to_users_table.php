<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_plesconnect_user')->default(false)->after('role')->index();
        });

        DB::table('users')->where('is_organizer', true)->update(['is_plesconnect_user' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_plesconnect_user']);
            $table->dropColumn('is_plesconnect_user');
        });
    }
};
