<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Claims a caller makes on a create operation. Two simultaneous submissions of the same form
     * both reach the API before either finishes, so the winner cannot be decided in PHP: the
     * unique index below is what makes exactly one of them the creator. `resource_id` is null
     * while the creating request is still in flight and set once it succeeds.
     */
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 60);
            $table->unsignedBigInteger('user_id');
            $table->string('key', 100);
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
