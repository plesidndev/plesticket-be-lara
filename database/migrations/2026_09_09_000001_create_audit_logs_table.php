<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Kept by value, not by foreign key: the record must survive the actor being deleted,
            // and their role at the time is part of what happened.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 255);
            $table->string('actor_role', 30);

            $table->string('action', 60);
            $table->string('subject_type', 40);

            // Nullable: a create knows its subject only after the fact, and some actions have none.
            $table->string('subject_id', 64)->nullable();
            $table->string('subject_label', 255)->nullable();

            $table->json('changes')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index('actor_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
