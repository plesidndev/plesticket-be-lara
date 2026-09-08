<?php

namespace Tests\Feature;

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

    public function test_it_creates_an_admin_account_with_a_super_admin_uid(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/users', [
            'name' => 'New Admin', 'email' => 'New.Admin@Example.com', 'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()
            ->assertJsonPath('data.role', 'SUPER_ADMIN')
            ->assertJsonPath('data.email', 'new.admin@example.com')
            ->assertJsonPath('data.is_active', true);

        $created = User::where('email', 'new.admin@example.com')->firstOrFail();
        $this->assertSame('SA'.sprintf('%04d', $created->id), $created->uid);
        $this->assertNotSame('password123', $created->password);
    }

    public function test_it_ignores_a_role_supplied_by_the_client_and_still_creates_an_admin(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/users', [
            'name' => 'Sneaky Member', 'email' => 'member@example.com', 'password' => 'password123',
            'password_confirmation' => 'password123', 'role' => 'REGISTERED_USER',
        ])->assertCreated()->assertJsonPath('data.role', 'SUPER_ADMIN');

        $created = User::where('email', 'member@example.com')->firstOrFail();
        $this->assertSame('SA'.sprintf('%04d', $created->id), $created->uid);
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
}
