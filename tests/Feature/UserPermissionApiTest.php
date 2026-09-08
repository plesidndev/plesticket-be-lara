<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPermissionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::create(['uid' => 'SA0001', 'name' => 'Super', 'email' => 'super@example.com', 'password' => 'secret', 'role' => 'SUPER_ADMIN']);
        $this->staff = User::create(['uid' => 'AD0001', 'name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'secret', 'role' => 'ADMIN']);
        $this->staff->permissions()->create(['permission' => Permission::ConsoleAccess->value, 'granted_at' => now()]);
    }

    public function test_it_returns_the_grants_alongside_the_catalog(): void
    {
        $this->actingAs($this->superAdmin, 'api')->getJson('/api/users/AD0001/permissions')
            ->assertOk()
            ->assertJsonPath('data.uid', 'AD0001')
            ->assertJsonPath('data.bypasses_checks', false)
            ->assertJsonPath('data.granted', ['console.access'])
            ->assertJsonCount(count(Permission::cases()), 'data.available')
            ->assertJsonPath('data.available.0.group', 'general');
    }

    public function test_it_replaces_the_grant_set_rather_than_merging(): void
    {
        $this->actingAs($this->superAdmin, 'api')->putJson('/api/users/AD0001/permissions', [
            'permissions' => ['console.access', 'events.view', 'events.moderate'],
        ])->assertOk()->assertJsonCount(3, 'data.granted');

        $this->actingAs($this->superAdmin, 'api')->putJson('/api/users/AD0001/permissions', [
            'permissions' => ['events.view'],
        ])->assertOk()->assertJsonPath('data.granted', ['events.view']);

        $this->assertSame(['events.view'], $this->staff->fresh()->permissionCodes());
    }

    public function test_it_records_who_granted_a_permission(): void
    {
        $this->actingAs($this->superAdmin, 'api')->putJson('/api/users/AD0001/permissions', [
            'permissions' => ['console.access', 'talents.view'],
        ])->assertOk();

        $this->assertSame(
            $this->superAdmin->id,
            $this->staff->permissions()->where('permission', 'talents.view')->value('granted_by'),
        );
    }

    public function test_an_empty_list_revokes_everything(): void
    {
        $this->actingAs($this->superAdmin, 'api')->putJson('/api/users/AD0001/permissions', ['permissions' => []])
            ->assertOk()->assertJsonPath('data.granted', []);

        $this->assertSame(0, $this->staff->permissions()->count());
    }

    public function test_it_rejects_a_permission_outside_the_catalog(): void
    {
        $this->actingAs($this->superAdmin, 'api')->putJson('/api/users/AD0001/permissions', [
            'permissions' => ['console.access', 'billing.refund_everything'],
        ])->assertStatus(422)->assertJsonValidationErrors(['permissions.1']);

        $this->assertSame(['console.access'], $this->staff->fresh()->permissionCodes());
    }

    public function test_it_refuses_to_write_grants_to_a_super_admin(): void
    {
        $this->actingAs($this->superAdmin, 'api')->putJson('/api/users/SA0001/permissions', [
            'permissions' => ['console.access'],
        ])->assertStatus(422)->assertJsonPath('errors.permissions.0', 'A super admin already holds every permission.');

        $this->assertSame(0, $this->superAdmin->permissions()->count());
    }

    public function test_it_refuses_to_let_an_admin_edit_its_own_grants(): void
    {
        $this->staff->permissions()->create(['permission' => Permission::UsersManage->value, 'granted_at' => now()]);

        // Neither losing access nor gaining it: editing your own grants is refused outright,
        // which closes self-escalation as well as self-lockout.
        $this->actingAs($this->staff->fresh(), 'api')->putJson('/api/users/AD0001/permissions', [
            'permissions' => ['console.access'],
        ])->assertStatus(422);

        $this->actingAs($this->staff->fresh(), 'api')->putJson('/api/users/AD0001/permissions', [
            'permissions' => ['console.access', 'users.manage', 'users.view_staff'],
        ])->assertStatus(422);

        $this->assertNotContains('users.view_staff', $this->staff->fresh()->permissionCodes());
    }

    public function test_an_admin_with_user_management_can_grant_to_someone_else(): void
    {
        $this->staff->permissions()->create(['permission' => Permission::UsersManage->value, 'granted_at' => now()]);
        $other = User::create(['uid' => 'AD0002', 'name' => 'Other', 'email' => 'other@example.com', 'password' => 'secret', 'role' => 'ADMIN']);

        $this->actingAs($this->staff->fresh(), 'api')->putJson('/api/users/AD0002/permissions', [
            'permissions' => ['console.access', 'events.view'],
        ])->assertOk();

        $this->assertEqualsCanonicalizing(['console.access', 'events.view'], $other->fresh()->permissionCodes());
    }

    public function test_it_denies_the_endpoint_without_user_management(): void
    {
        $this->actingAs($this->staff, 'api')->getJson('/api/users/AD0001/permissions')->assertForbidden();
        $this->actingAs($this->staff, 'api')->putJson('/api/users/AD0001/permissions', ['permissions' => []])->assertForbidden();
    }

    public function test_it_404s_an_unknown_account(): void
    {
        $this->actingAs($this->superAdmin, 'api')->getJson('/api/users/NOPE/permissions')->assertNotFound();
    }
}
