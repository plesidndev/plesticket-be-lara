<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionGateTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::create(['uid' => 'SA0001', 'name' => 'Super', 'email' => 'super@example.com', 'password' => 'secret', 'role' => 'SUPER_ADMIN']);
    }

    public function test_a_super_admin_passes_every_gate_holding_no_grants(): void
    {
        $this->assertSame(0, $this->superAdmin->permissions()->count());

        foreach (Permission::cases() as $permission) {
            $this->assertTrue($this->superAdmin->hasPermission($permission), $permission->value);
        }

        $this->actingAs($this->superAdmin, 'api')->getJson('/api/admin/summary')->assertOk();
        $this->actingAs($this->superAdmin, 'api')->getJson('/api/users')->assertOk();
    }

    public function test_a_grant_opens_exactly_one_gate(): void
    {
        $staff = $this->staff([Permission::ConsoleAccess, Permission::EventsView]);

        $this->actingAs($staff, 'api')->getJson('/api/admin/events')->assertOk();
        $this->actingAs($staff, 'api')->getJson('/api/admin/summary')->assertForbidden();
        $this->actingAs($staff, 'api')->getJson('/api/admin/talents')->assertForbidden();
    }

    public function test_viewing_does_not_imply_moderating(): void
    {
        $staff = $this->staff([Permission::ConsoleAccess, Permission::EventsView]);

        $this->actingAs($staff, 'api')->postJson('/api/admin/events/1/verify')->assertForbidden();
    }

    public function test_a_refusal_names_the_missing_permission(): void
    {
        $staff = $this->staff([Permission::ConsoleAccess]);

        $this->actingAs($staff, 'api')->getJson('/api/admin/summary')
            ->assertForbidden()
            ->assertJsonPath('errors.code', 'MISSING_PERMISSION')
            ->assertJsonPath('errors.required.0', 'summary.view');
    }

    public function test_a_staff_account_with_no_grants_reaches_nothing(): void
    {
        $staff = $this->staff([]);

        $this->actingAs($staff, 'api')->getJson('/api/admin/summary')->assertForbidden();
        $this->actingAs($staff, 'api')->getJson('/api/admin/events')->assertForbidden();
        $this->actingAs($staff, 'api')->getJson('/api/users')->assertForbidden();
    }

    public function test_users_view_staff_widens_the_directory(): void
    {
        User::create(['uid' => 'U000001', 'name' => 'Member', 'email' => 'member@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER']);
        $staff = $this->staff([Permission::ConsoleAccess, Permission::UsersView]);

        $this->actingAs($staff, 'api')->getJson('/api/users')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($staff, 'api')->getJson('/api/users/SA0001')->assertNotFound();

        $staff->permissions()->create(['permission' => Permission::UsersViewStaff->value, 'granted_at' => now()]);

        $this->actingAs($staff->fresh(), 'api')->getJson('/api/users')->assertOk()->assertJsonCount(3, 'data');
        $this->actingAs($staff->fresh(), 'api')->getJson('/api/users/SA0001')->assertOk();
    }

    public function test_a_revoked_grant_takes_effect_on_the_next_request(): void
    {
        $staff = $this->staff([Permission::ConsoleAccess, Permission::EventsView]);

        $this->actingAs($staff, 'api')->getJson('/api/admin/events')->assertOk();

        $staff->permissions()->where('permission', Permission::EventsView->value)->delete();

        $this->actingAs($staff->fresh(), 'api')->getJson('/api/admin/events')->assertForbidden();
    }

    public function test_a_member_holding_grants_still_reaches_nothing(): void
    {
        $member = User::create(['uid' => 'U000002', 'name' => 'Member', 'email' => 'm2@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER']);

        foreach ([Permission::ConsoleAccess, Permission::SummaryView, Permission::UsersManage] as $permission) {
            $member->permissions()->create(['permission' => $permission->value, 'granted_at' => now()]);
        }

        $member = $member->fresh();

        // Grants are inert on a non-staff account, so demoting somebody actually revokes their
        // access rather than leaving stale rows that keep working.
        $this->assertFalse($member->hasPermission(Permission::SummaryView));
        $this->actingAs($member, 'api')->getJson('/api/admin/summary')->assertForbidden();
        $this->actingAs($member, 'api')->getJson('/api/users')->assertForbidden();
    }

    public function test_demoting_a_staff_account_revokes_its_access_without_deleting_grants(): void
    {
        $staff = $this->staff([Permission::ConsoleAccess, Permission::SummaryView]);

        $this->actingAs($staff, 'api')->getJson('/api/admin/summary')->assertOk();

        $staff->update(['role' => UserRole::RegisteredUser]);

        $this->actingAs($staff->fresh(), 'api')->getJson('/api/admin/summary')->assertForbidden();
        $this->assertSame(2, $staff->permissions()->count());
    }

    public function test_a_new_admin_starts_with_the_overview_and_nothing_else(): void
    {
        $this->actingAs($this->superAdmin, 'api')->postJson('/api/users', [
            'name' => 'Fresh', 'email' => 'fresh@example.com', 'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()
            ->assertJsonPath('data.role', 'ADMIN')
            ->assertJsonPath('data.bypasses_permission_checks', false)
            ->assertJsonPath('data.permissions', ['console.access', 'summary.view']);

        $created = User::where('email', 'fresh@example.com')->firstOrFail();

        $this->assertEqualsCanonicalizing(['console.access', 'summary.view'], $created->permissionCodes());
        $this->assertEqualsCanonicalizing(
            array_map(static fn (Permission $p): string => $p->value, UserRole::Admin->defaultPermissions()),
            $created->permissionCodes(),
        );
    }

    public function test_a_new_admin_reaches_the_overview_but_no_other_screen(): void
    {
        $this->actingAs($this->superAdmin, 'api')->postJson('/api/users', [
            'name' => 'Fresh', 'email' => 'fresh@example.com', 'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $fresh = User::where('email', 'fresh@example.com')->firstOrFail();

        $this->actingAs($fresh, 'api')->getJson('/api/admin/summary')->assertOk();

        foreach (['/api/admin/events', '/api/admin/talents', '/api/admin/categories', '/api/admin/operations/refunds', '/api/users'] as $route) {
            $this->actingAs($fresh, 'api')->getJson($route)->assertForbidden();
        }
    }

    public function test_the_resource_reports_the_full_catalog_for_a_super_admin(): void
    {
        $this->actingAs($this->superAdmin, 'api')->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.bypasses_permission_checks', true)
            ->assertJsonCount(count(Permission::cases()), 'data.permissions');
    }

    /** @param list<Permission> $permissions */
    private function staff(array $permissions): User
    {
        $user = User::create(['uid' => 'AD0001', 'name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'secret', 'role' => 'ADMIN']);

        foreach ($permissions as $permission) {
            $user->permissions()->create(['permission' => $permission->value, 'granted_at' => now()]);
        }

        return $user->load('permissions');
    }
}
