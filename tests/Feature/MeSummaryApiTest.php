<?php

namespace Tests\Feature;

use App\Enums\CatalogStatus;
use App\Models\CatalogRelease;
use App\Models\Category;
use App\Models\Event;
use App\Models\Talent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeSummaryApiTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = User::create(['uid' => 'U000001', 'name' => 'Dewi', 'email' => 'dewi@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER', 'is_plesconnect_user' => true, 'is_organizer' => true]);
        $this->other = User::create(['uid' => 'U000002', 'name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER', 'is_plesconnect_user' => true, 'is_organizer' => true]);
        Category::create(['name' => 'Music', 'is_active' => true]);
    }

    private function release(User $owner, CatalogStatus $status, string $title): void
    {
        CatalogRelease::create(['user_id' => $owner->id, 'type' => 'music', 'title' => $title, 'metadata' => [], 'status' => $status]);
    }

    private function event(User $owner, string $id, string $status): void
    {
        Event::create([
            'event_id' => $id, 'user_id' => $owner->id, 'title' => 'Show '.$id, 'slug' => 'show-'.strtolower($id),
            'category' => 'Music', 'pic_name' => 'PIC', 'pic_identity_type' => 'ktp', 'pic_identity_number' => '3173000000000001',
            'start_date' => '2026-10-01', 'end_date' => '2026-10-01', 'verification_status' => $status,
        ]);
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/me/summary')->assertUnauthorized();
    }

    public function test_it_counts_the_creators_own_catalog_work_by_status(): void
    {
        $this->release($this->creator, CatalogStatus::ChangesRequested, 'Bounced');
        $this->release($this->creator, CatalogStatus::Draft, 'Draft one');
        $this->release($this->creator, CatalogStatus::Draft, 'Draft two');
        $this->release($this->creator, CatalogStatus::WaitingForReview, 'Queued');
        $this->release($this->creator, CatalogStatus::UnderReview, 'Being reviewed');
        $this->release($this->creator, CatalogStatus::Live, 'Published');

        $this->actingAs($this->creator, 'api')->getJson('/api/me/summary')->assertOk()
            ->assertJsonPath('data.releases_changes_requested', 1)
            ->assertJsonPath('data.releases_draft', 2)
            ->assertJsonPath('data.releases_in_review', 2)
            ->assertJsonPath('data.releases_live', 1);
    }

    public function test_it_never_counts_another_creators_work(): void
    {
        $this->release($this->other, CatalogStatus::ChangesRequested, 'Not mine');
        $this->release($this->other, CatalogStatus::Draft, 'Also not mine');
        Talent::create(['name' => 'Their Band', 'slug' => 'their-band', 'is_verified' => false, 'submitted_by' => $this->other->id]);
        $this->event($this->other, 'EVT9999', 'pending');

        $this->actingAs($this->creator, 'api')->getJson('/api/me/summary')->assertOk()
            ->assertJsonPath('data.releases_changes_requested', 0)
            ->assertJsonPath('data.releases_draft', 0)
            ->assertJsonPath('data.talents_total', 0)
            ->assertJsonPath('data.events_total', 0);
    }

    public function test_it_counts_talents_and_those_awaiting_verification(): void
    {
        Talent::create(['name' => 'Verified Act', 'slug' => 'verified-act', 'is_verified' => true, 'submitted_by' => $this->creator->id]);
        Talent::create(['name' => 'Pending Act', 'slug' => 'pending-act', 'is_verified' => false, 'submitted_by' => $this->creator->id]);

        $this->actingAs($this->creator, 'api')->getJson('/api/me/summary')->assertOk()
            ->assertJsonPath('data.talents_total', 2)
            ->assertJsonPath('data.talents_awaiting_verification', 1);
    }

    public function test_it_counts_events_and_those_pending_review(): void
    {
        $this->event($this->creator, 'EVT0001', 'pending');
        $this->event($this->creator, 'EVT0002', 'verified');

        $this->actingAs($this->creator, 'api')->getJson('/api/me/summary')->assertOk()
            ->assertJsonPath('data.events_total', 2)
            ->assertJsonPath('data.events_pending_review', 1);
    }

    public function test_a_creator_without_organizer_access_simply_reports_no_events(): void
    {
        $buyer = User::create(['uid' => 'U000003', 'name' => 'Sari', 'email' => 'sari@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER', 'is_plesconnect_user' => true]);

        $this->actingAs($buyer, 'api')->getJson('/api/me/summary')->assertOk()
            ->assertJsonPath('data.events_total', 0)
            ->assertJsonPath('data.events_pending_review', 0)
            ->assertJsonPath('data.talents_total', 0);
    }
}
