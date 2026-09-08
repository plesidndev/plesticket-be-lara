<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('permission', 64);
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at')->useCurrent();

            $table->unique(['user_id', 'permission']);
            $table->index('permission');
        });

        $this->backfill();
    }

    /**
     * Give every existing ADMIN what the role gates already allowed it before this migration, so
     * the switch from role gates to permission gates changes nothing on deploy.
     *
     * The list is spelled out here rather than read from UserRole::defaultPermissions(): that
     * preset describes what a *new* admin should start with and is expected to change, while this
     * is a record of what these accounts could already reach. Re-running an old migration must
     * not hand existing admins a different set because the preset moved on.
     *
     * SUPER_ADMIN is skipped deliberately: it bypasses the check, and writing rows for it would
     * imply they could be revoked.
     */
    private function backfill(): void
    {
        $permissions = [
            'console.access',
            'summary.view',
            'operations.view',
            'categories.view',
            'categories.manage',
            'events.view',
            'events.moderate',
            'talents.view',
            'talents.moderate',
            'catalog.view',
            'catalog.manage',
            'catalog_masters.manage',
            'users.view',
        ];

        $now = now();

        DB::table('users')->where('role', UserRole::Admin->value)->orderBy('id')
            ->chunkById(100, function ($admins) use ($permissions, $now): void {
                $rows = [];

                foreach ($admins as $admin) {
                    foreach ($permissions as $permission) {
                        $rows[] = ['user_id' => $admin->id, 'permission' => $permission, 'granted_at' => $now];
                    }
                }

                DB::table('user_permissions')->insertOrIgnore($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
    }
};
