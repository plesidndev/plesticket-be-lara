<?php

namespace Tests\Feature;

use App\Models\TalentCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TalentCategoryApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $organizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['uid' => 'SA0001', 'name' => 'Super Admin', 'email' => 'admin@example.com', 'password' => 'secret', 'role' => 'SUPER_ADMIN']);
        $this->organizer = User::create(['uid' => 'U000001', 'name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER', 'is_organizer' => true]);
    }

    public function test_the_migration_seeds_the_categories_talents_already_use(): void
    {
        $this->assertSame(
            ['music', 'band', 'dj', 'comedian', 'speaker', 'mc', 'dancer', 'other'],
            TalentCategory::query()->orderBy('sort_order')->pluck('code')->all(),
        );
    }

    public function test_the_public_list_returns_active_categories_in_sort_order(): void
    {
        TalentCategory::where('code', 'other')->update(['is_active' => false]);

        $response = $this->getJson('/api/talent-categories')->assertOk();

        $this->assertSame('music', $response->json('data.0.code'));
        $this->assertNotContains('other', array_column($response->json('data'), 'code'));
    }

    public function test_the_admin_list_includes_inactive_categories(): void
    {
        TalentCategory::where('code', 'other')->update(['is_active' => false]);

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/talent-categories')
            ->assertOk()
            ->assertJsonCount(8, 'data');
    }

    public function test_the_admin_list_is_closed_to_non_admins(): void
    {
        $this->actingAs($this->organizer, 'api')->getJson('/api/admin/talent-categories')->assertForbidden();
        // Guests are refused with 403 here, matching the role middleware used across the admin routes.
        $this->getJson('/api/admin/talent-categories')->assertForbidden();
    }

    public function test_an_admin_creates_a_category(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/admin/talent-categories', ['code' => 'magician', 'name' => 'Magician'])
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'magician')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('talent_categories', ['code' => 'magician']);
    }

    public function test_creating_rejects_a_malformed_code_and_a_duplicate_name(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/admin/talent-categories', ['code' => 'Not A Code', 'name' => 'Nope'])
            ->assertStatus(422)->assertJsonValidationErrors('code');

        $this->actingAs($this->admin, 'api')->postJson('/api/admin/talent-categories', ['code' => 'music-2', 'name' => 'music'])
            ->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_an_admin_renames_and_deactivates_a_category(): void
    {
        $this->actingAs($this->admin, 'api')->patchJson('/api/admin/talent-categories/dj', ['name' => 'Disc Jockey', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Disc Jockey')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_a_category_code_cannot_be_rewritten_on_update(): void
    {
        $this->actingAs($this->admin, 'api')->patchJson('/api/admin/talent-categories/dj', ['code' => 'deejay'])
            ->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_talent_submission_accepts_a_seeded_category(): void
    {
        $this->actingAs($this->organizer, 'api')->postJson('/api/talents', ['name' => 'Sunset Band', 'type' => 'group', 'category' => 'band'])
            ->assertStatus(201);
    }

    public function test_talent_submission_rejects_an_unknown_or_inactive_category(): void
    {
        $this->actingAs($this->organizer, 'api')->postJson('/api/talents', ['name' => 'Ghost', 'type' => 'group', 'category' => 'nonexistent'])
            ->assertStatus(422)->assertJsonValidationErrors('category');

        TalentCategory::where('code', 'dancer')->update(['is_active' => false]);

        $this->actingAs($this->organizer, 'api')->postJson('/api/talents', ['name' => 'Ghost', 'type' => 'group', 'category' => 'dancer'])
            ->assertStatus(422)->assertJsonValidationErrors('category');
    }

    public function test_a_newly_created_category_is_immediately_usable_on_a_talent(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/admin/talent-categories', ['code' => 'magician', 'name' => 'Magician'])->assertStatus(201);

        $this->actingAs($this->organizer, 'api')->postJson('/api/talents', ['name' => 'The Great Budi', 'type' => 'personal', 'category' => 'magician'])
            ->assertStatus(201)
            ->assertJsonPath('data.category', 'magician');
    }
}
