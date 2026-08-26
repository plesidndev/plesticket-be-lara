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
            // A flag rather than a role: one person can organise events and
            // still buy tickets to someone else's.
            $table->boolean('is_organizer')->default(false)->after('role');
            $table->index('is_organizer');
        });

        // Anyone who already owns an event was an organizer before this column
        // existed. Without this they would start getting 403s on routes that
        // worked yesterday. Soft-deleted events count — the account was still
        // used as an organizer.
        $owners = DB::table('events')->distinct()->pluck('user_id')->filter()->all();

        if ($owners) {
            DB::table('users')->whereIn('id', $owners)->update(['is_organizer' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_organizer']);
            $table->dropColumn('is_organizer');
        });
    }
};
