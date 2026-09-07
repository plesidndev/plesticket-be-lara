<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The category values talents were restricted to while `talents.category`
     * was validated against a hard-coded `in:` rule.
     *
     * @var array<string, string>
     */
    private const SEED = [
        'music' => 'Music',
        'band' => 'Band',
        'dj' => 'DJ',
        'comedian' => 'Comedian',
        'speaker' => 'Speaker',
        'mc' => 'MC',
        'dancer' => 'Dancer',
        'other' => 'Other',
    ];

    public function up(): void
    {
        Schema::create('talent_categories', function (Blueprint $table) {
            $table->string('code', 40)->primary();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        /**
         * Seeded here rather than in a seeder because existing talents already
         * hold these codes. An empty table would reject every talent create and
         * update the moment this migration is deployed.
         */
        $now = now();
        $rows = [];
        $sortOrder = 0;
        foreach (self::SEED as $code => $name) {
            $rows[] = [
                'code' => $code, 'name' => $name, 'is_active' => true,
                'sort_order' => ++$sortOrder, 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        DB::table('talent_categories')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_categories');
    }
};
