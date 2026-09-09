<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['uid' => 'SA0001', 'name' => 'Super Admin', 'email' => 'admin@example.com', 'password' => 'secret', 'role' => 'SUPER_ADMIN']);
        $this->member = User::create(['uid' => 'U000001', 'name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER']);
    }

    public function test_it_records_who_deleted_an_account_and_what_it_was(): void
    {
        $this->actingAs($this->admin, 'api')->deleteJson('/api/users/U000001')->assertOk();

        $log = AuditLog::where('action', 'user.deleted')->firstOrFail();

        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertSame('Super Admin', $log->actor_name);
        $this->assertSame('SUPER_ADMIN', $log->actor_role);
        $this->assertSame('U000001', $log->subject_id);
        $this->assertSame('Budi', $log->subject_label);
        $this->assertSame('budi@example.com', $log->changes['email']);
    }

    public function test_it_records_only_the_fields_an_update_actually_changed(): void
    {
        $this->actingAs($this->admin, 'api')->putJson('/api/users/U000001', ['name' => 'Budi Renamed', 'email' => 'budi@example.com'])->assertOk();

        $log = AuditLog::where('action', 'user.updated')->firstOrFail();

        // Email was submitted unchanged, so it must not read as an email change.
        $this->assertSame(['name'], $log->changes['fields']);
        $this->assertSame('Budi', $log->changes['before']['name']);
        $this->assertSame('Budi Renamed', $log->changes['after']['name']);
    }

    public function test_it_never_writes_a_password_into_the_log(): void
    {
        $this->actingAs($this->admin, 'api')->putJson('/api/users/U000001', [
            'password' => 'brandnewpass', 'password_confirmation' => 'brandnewpass',
        ])->assertOk();

        $log = AuditLog::where('action', 'user.updated')->firstOrFail();

        $this->assertContains('password', $log->changes['fields']);
        $this->assertStringNotContainsString('brandnewpass', json_encode($log->changes));
    }

    public function test_it_records_a_creation_with_the_role_granted(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/users', [
            'name' => 'New Admin', 'email' => 'new@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertCreated();

        $log = AuditLog::where('action', 'user.created')->firstOrFail();

        // Whatever role the create flow granted is what the log records — currently ADMIN by default.
        $this->assertSame('ADMIN', $log->changes['role']);
        $this->assertSame('New Admin', $log->subject_label);
    }

    public function test_it_records_which_permissions_moved(): void
    {
        $staff = User::create(['uid' => 'AD0002', 'name' => 'Limited', 'email' => 'limited@example.com', 'password' => 'secret', 'role' => 'ADMIN']);
        $staff->permissions()->create(['permission' => 'console.access']);
        $staff->permissions()->create(['permission' => 'events.view']);

        $this->actingAs($this->admin, 'api')->putJson('/api/users/AD0002/permissions', [
            'permissions' => ['console.access', 'talents.view'],
        ])->assertOk();

        $log = AuditLog::where('action', 'user.permissions_synced')->firstOrFail();

        $this->assertSame(['talents.view'], $log->changes['added']);
        $this->assertSame(['events.view'], $log->changes['removed']);
    }

    public function test_the_record_outlives_the_actor(): void
    {
        $staff = User::create(['uid' => 'AD0003', 'name' => 'Departing Admin', 'email' => 'dep@example.com', 'password' => 'secret', 'role' => 'SUPER_ADMIN']);

        $this->actingAs($staff, 'api')->deleteJson('/api/users/U000001')->assertOk();
        $staff->delete();

        $log = AuditLog::where('action', 'user.deleted')->firstOrFail();

        // The foreign key nulls out, but who did it is still readable.
        $this->assertNull($log->fresh()->actor_id);
        $this->assertSame('Departing Admin', $log->actor_name);
    }

    public function test_it_filters_the_log_and_lists_the_actions_recorded(): void
    {
        $this->actingAs($this->admin, 'api')->putJson('/api/users/U000001', ['name' => 'Renamed'])->assertOk();
        $this->actingAs($this->admin, 'api')->deleteJson('/api/users/U000001')->assertOk();

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/audit-logs')->assertOk()
            ->assertJsonCount(2, 'data')
            // Newest first.
            ->assertJsonPath('data.0.action', 'user.deleted');

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/audit-logs?action=user.updated')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/audit-logs?subject_id=U000001')
            ->assertOk()->assertJsonCount(2, 'data');

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/audit-logs/actions')->assertOk()
            ->assertJsonPath('data', ['user.deleted', 'user.updated']);
    }

    public function test_reading_the_log_requires_the_audit_grant(): void
    {
        $limited = User::create(['uid' => 'AD0004', 'name' => 'Limited', 'email' => 'lim@example.com', 'password' => 'secret', 'role' => 'ADMIN']);
        $limited->permissions()->create(['permission' => 'console.access']);
        $limited->permissions()->create(['permission' => 'users.manage']);

        $this->actingAs($limited, 'api')->getJson('/api/admin/audit-logs')->assertForbidden();

        $limited->permissions()->create(['permission' => 'audit.view']);
        $this->actingAs($limited->fresh(), 'api')->getJson('/api/admin/audit-logs')->assertOk();
    }

    public function test_reads_are_not_audited(): void
    {
        $this->actingAs($this->admin, 'api')->getJson('/api/users')->assertOk();
        $this->actingAs($this->admin, 'api')->getJson('/api/users/U000001')->assertOk();

        $this->assertSame(0, AuditLog::count());
    }
}
