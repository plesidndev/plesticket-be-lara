<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['uid' => 'SA0001', 'name' => 'Super Admin', 'email' => 'admin@example.com', 'password' => 'secret', 'role' => 'SUPER_ADMIN']);
        $this->member = User::create(['uid' => 'U000001', 'name' => 'Budi Santoso', 'email' => 'budi@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER']);
    }

    public function test_it_defaults_a_new_staff_account_to_admin(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/users', [
            'name' => 'New Admin', 'email' => 'New.Admin@Example.com', 'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()
            ->assertJsonPath('data.role', 'ADMIN')
            ->assertJsonPath('data.role_label', 'Admin')
            ->assertJsonPath('data.email', 'new.admin@example.com')
            ->assertJsonPath('data.is_active', true);

        $created = User::where('email', 'new.admin@example.com')->firstOrFail();
        $this->assertSame('AD'.sprintf('%04d', $created->id), $created->uid);
        $this->assertNotSame('password123', $created->password);
    }

    public function test_it_creates_a_super_admin_when_the_role_is_asked_for(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/users', [
            'name' => 'Second Super', 'email' => 'super2@example.com', 'password' => 'password123',
            'password_confirmation' => 'password123', 'role' => 'SUPER_ADMIN',
        ])->assertCreated()->assertJsonPath('data.role', 'SUPER_ADMIN');

        $created = User::where('email', 'super2@example.com')->firstOrFail();
        $this->assertSame('SA'.sprintf('%04d', $created->id), $created->uid);
    }

    public function test_it_rejects_a_non_staff_role_on_create(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/users', [
            'name' => 'Sneaky Member', 'email' => 'member@example.com', 'password' => 'password123',
            'password_confirmation' => 'password123', 'role' => 'REGISTERED_USER',
        ])->assertStatus(422)->assertJsonValidationErrors(['role']);

        $this->assertSame(2, User::count());
    }

    public function test_it_rejects_duplicate_emails_and_unconfirmed_passwords(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/users', [
            'name' => 'Clash', 'email' => 'budi@example.com', 'password' => 'password123',
            'password_confirmation' => 'nope',
        ])->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);

        $this->assertSame(2, User::count());
    }

    public function test_it_filters_the_directory_by_role(): void
    {
        $this->actingAs($this->admin, 'api')->getJson('/api/users?role=SUPER_ADMIN')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.uid', 'SA0001');

        $this->actingAs($this->admin, 'api')->getJson('/api/users?role=REGISTERED_USER')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.uid', 'U000001');
    }

    public function test_it_filters_the_directory_by_several_roles_at_once(): void
    {
        User::create(['uid' => 'AD0001', 'name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'secret', 'role' => 'ADMIN']);

        $this->actingAs($this->admin, 'api')->getJson('/api/users?role=SUPER_ADMIN,ADMIN')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
    }

    public function test_it_changes_a_role_through_update(): void
    {
        $this->actingAs($this->admin, 'api')->putJson('/api/users/U000001', ['role' => 'SUPER_ADMIN'])
            ->assertOk()->assertJsonPath('data.role', 'SUPER_ADMIN');

        $this->assertSame('SUPER_ADMIN', $this->member->fresh()->role->value);
    }

    public function test_it_denies_user_management_to_non_admins(): void
    {
        $this->actingAs($this->member, 'api')->postJson('/api/users', [
            'name' => 'Sneaky', 'email' => 'sneaky@example.com', 'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertForbidden();

        $this->assertSame(2, User::count());
    }

    public function test_it_updates_every_editable_profile_field(): void
    {
        $this->actingAs($this->admin, 'api')->putJson('/api/users/U000001', [
            'name' => 'Budi Updated', 'username' => 'budi_updated', 'email' => 'Budi.New@Example.com',
            'phone' => '08111', 'date_of_birth' => '1992-03-04', 'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.username', 'budi_updated')
            ->assertJsonPath('data.email', 'budi.new@example.com')
            ->assertJsonPath('data.date_of_birth', '1992-03-04')
            ->assertJsonPath('data.is_active', false);

        $this->assertSame('08111', $this->member->fresh()->phone);
    }

    public function test_it_resets_a_password_without_touching_other_fields(): void
    {
        $this->actingAs($this->admin, 'api')->putJson('/api/users/U000001', [
            'password' => 'brandnewpass', 'password_confirmation' => 'brandnewpass',
        ])->assertOk();

        $fresh = $this->member->fresh();
        $this->assertTrue(Hash::check('brandnewpass', $fresh->password));
        $this->assertSame('Budi Santoso', $fresh->name);
        $this->assertSame('budi@example.com', $fresh->email);
    }

    public function test_it_rejects_an_unconfirmed_password_and_a_taken_email(): void
    {
        $this->actingAs($this->admin, 'api')->putJson('/api/users/U000001', [
            'email' => 'admin@example.com', 'password' => 'brandnewpass', 'password_confirmation' => 'mismatch',
        ])->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);

        $this->assertSame('budi@example.com', $this->member->fresh()->email);
    }

    public function test_it_allows_an_account_to_keep_its_own_email_and_username(): void
    {
        $this->actingAs($this->admin, 'api')->putJson('/api/users/U000001', [
            'name' => 'Budi Renamed', 'email' => 'budi@example.com',
        ])->assertOk()->assertJsonPath('data.name', 'Budi Renamed');
    }

    public function test_an_admin_reaches_the_console_but_cannot_write_to_user_management(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff, 'api')->getJson('/api/admin/categories')->assertOk();
        $this->actingAs($staff, 'api')->getJson('/api/admin/events')->assertOk();

        $this->actingAs($staff, 'api')->postJson('/api/users', [
            'name' => 'Nope', 'email' => 'nope@example.com', 'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertForbidden();
        $this->actingAs($staff, 'api')->putJson('/api/users/AD0001', ['role' => 'SUPER_ADMIN'])->assertForbidden();
        $this->actingAs($staff, 'api')->deleteJson('/api/users/U000001')->assertForbidden();

        $this->assertSame('ADMIN', $staff->fresh()->role->value);
    }

    public function test_an_admin_sees_only_members_in_the_directory(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff, 'api')->getJson('/api/users')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.uid', 'U000001');
    }

    public function test_an_admin_cannot_widen_the_directory_back_to_staff(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff, 'api')->getJson('/api/users?role=SUPER_ADMIN')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.role', 'REGISTERED_USER');

        $this->actingAs($staff, 'api')->getJson('/api/users?role=SUPER_ADMIN,ADMIN')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.role', 'REGISTERED_USER');
    }

    public function test_an_admin_cannot_open_a_staff_account(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff, 'api')->getJson('/api/users/U000001')->assertOk();
        $this->actingAs($staff, 'api')->getJson('/api/users/SA0001')->assertNotFound();
        $this->actingAs($staff, 'api')->getJson('/api/users/AD0001')->assertNotFound();
    }

    /**
     * An admin granted the read-only console these tests are about: it can look at the member
     * directory but holds neither users.manage nor users.view_staff.
     *
     * Spelled out rather than taken from UserRole::defaultPermissions(), which is deliberately
     * narrower — a new admin starts with the overview alone. These tests are about what the
     * directory shows a reader, not about what the preset hands out.
     */
    private function staff(array $permissions = []): User
    {
        $user = User::create(['uid' => 'AD0001', 'name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'secret', 'role' => 'ADMIN']);

        $codes = $permissions !== [] ? $permissions : [
            Permission::ConsoleAccess->value,
            Permission::SummaryView->value,
            Permission::CategoriesView->value,
            Permission::EventsView->value,
            Permission::UsersView->value,
        ];

        $user->permissions()->createMany(array_map(
            static fn (string $code): array => ['permission' => $code, 'granted_at' => now()],
            $codes,
        ));

        return $user->load('permissions');
    }
}
