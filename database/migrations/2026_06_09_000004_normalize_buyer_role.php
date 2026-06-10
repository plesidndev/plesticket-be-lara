<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'BUYER')->update(['role' => 'REGISTERED_USER']);
    }

    public function down(): void
    {
        // Intentionally irreversible — BUYER role no longer exists
    }
};