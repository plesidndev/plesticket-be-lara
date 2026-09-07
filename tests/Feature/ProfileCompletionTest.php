<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_complete_only_their_own_profile(): void
    {
        $user = User::factory()->create(['is_plesconnect_user' => true, 'is_organizer' => false]);
        $other = User::factory()->create(['name' => 'Other User']);

        $this->actingAs($user->fresh(), 'api')->postJson('/api/profile', [
            'name' => 'Updated Name', 'username' => 'my_username', 'phone' => '001234567',
            'date_of_birth' => '1995-01-02', 'email' => 'changed@example.test',
            'id' => $other->id, 'role' => 'SUPER_ADMIN', 'is_organizer' => true,
        ])->assertOk()->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.has_completed_profile', true)->assertJsonPath('data.is_organizer', false)
            ->assertJsonPath('data.email', $user->email);

        $this->assertSame('Updated Name', $user->fresh()->name);
        $this->assertSame('001234567', $user->fresh()->phone);
        $this->assertNotNull($user->fresh()->profile_completed_at);
        $this->assertSame('Other User', $other->fresh()->name);
        $this->assertNotSame('SUPER_ADMIN', $user->fresh()->role->value);
    }

    public function test_profile_requires_authentication(): void
    {
        $this->postJson('/api/profile', ['name' => 'Someone'])->assertUnauthorized();
    }

    public function test_profile_checks_duplicate_username_and_invalid_birth_date(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['username' => 'taken']);
        $this->actingAs($user->fresh(), 'api')->postJson('/api/profile', [
            'name' => 'Name', 'username' => 'taken', 'date_of_birth' => '2099-01-01',
        ])->assertUnprocessable()->assertJsonValidationErrors(['username', 'date_of_birth']);
        $this->assertNull($user->fresh()->profile_completed_at);
    }

    public function test_an_existing_username_can_be_retained(): void
    {
        $user = User::factory()->create(['username' => 'mine']);
        $this->actingAs($user->fresh(), 'api')->postJson('/api/profile', ['name' => 'Name', 'username' => 'mine'])
            ->assertOk()->assertJsonPath('data.username', 'mine');
    }

    public function test_profile_photo_and_details_are_saved_together(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user->fresh(), 'api')->post('/api/profile', [
            'name' => 'Photo User', 'photo' => UploadedFile::fake()->image('photo.png'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.name', 'Photo User')
            ->assertJsonPath('data.has_completed_profile', true);
        Storage::disk('public')->assertExists($user->fresh()->photo);
    }
}
