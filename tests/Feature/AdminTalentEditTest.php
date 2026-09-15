<?php

namespace Tests\Feature;

use App\Models\Talent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TalentService::update() has always allowed an admin caller, but the only route reaching it was
 * gated by `eo`, which refuses staff accounts that are not themselves event organizers. These
 * cover the admin route that makes that branch reachable without widening the EO gate.
 */
class AdminTalentEditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private User $otherOrganizer;

    private Talent $talent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['uid' => 'SA0001', 'name' => 'Super Admin', 'email' => 'admin@example.com', 'password' => 'secret', 'role' => 'SUPER_ADMIN']);
        $this->owner = User::create(['uid' => 'U000001', 'name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER', 'is_organizer' => true]);
        $this->otherOrganizer = User::create(['uid' => 'U000002', 'name' => 'Sari', 'email' => 'sari@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER', 'is_organizer' => true]);
        $this->talent = Talent::create(['name' => 'Sunset Band', 'slug' => 'sunset-band', 'type' => 'group', 'category' => 'band', 'submitted_by' => $this->owner->id]);
    }

    public function test_an_admin_edits_a_talent_they_do_not_own(): void
    {
        $this->actingAs($this->admin, 'api')
            ->putJson("/api/admin/talents/{$this->talent->id}", ['name' => 'Sunset Band Live', 'bio' => 'Corrected by the review team.'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Sunset Band Live');

        $this->assertSame('Corrected by the review team.', $this->talent->fresh()->bio);
    }

    public function test_the_admin_route_refuses_an_organizer_even_for_their_own_talent(): void
    {
        $this->actingAs($this->owner, 'api')
            ->putJson("/api/admin/talents/{$this->talent->id}", ['name' => 'Renamed'])
            ->assertForbidden();

        $this->assertSame('Sunset Band', $this->talent->fresh()->name);
    }

    public function test_an_organizer_still_edits_their_own_talent_on_the_eo_route(): void
    {
        $this->actingAs($this->owner, 'api')
            ->putJson("/api/talents/{$this->talent->id}", ['name' => 'Sunset Band Acoustic'])
            ->assertOk();

        $this->assertSame('Sunset Band Acoustic', $this->talent->fresh()->name);
    }

    public function test_an_organizer_cannot_edit_someone_elses_talent(): void
    {
        $this->actingAs($this->otherOrganizer, 'api')
            ->putJson("/api/talents/{$this->talent->id}", ['name' => 'Hijacked'])
            ->assertForbidden();

        $this->assertSame('Sunset Band', $this->talent->fresh()->name);
    }

    /**
     * The admin console loads the profile through the public show route before editing it. That
     * route has no auth middleware, so if the api guard did not resolve the bearer token there,
     * contact would come back null, the edit form would render blank contact fields, and saving
     * would erase details the creator entered.
     */
    public function test_an_admin_reading_a_talent_still_receives_the_contact_block(): void
    {
        $this->talent->update(['contact_name' => 'Manager', 'contact_phone' => '0012345', 'contact_email' => 'manager@example.com']);

        $this->actingAs($this->admin, 'api')->getJson("/api/talents/{$this->talent->id}")
            ->assertOk()
            ->assertJsonPath('data.contact.name', 'Manager')
            ->assertJsonPath('data.contact.email', 'manager@example.com');
    }

    public function test_an_anonymous_reader_never_receives_the_contact_block(): void
    {
        $this->talent->update(['contact_email' => 'manager@example.com']);

        $this->getJson("/api/talents/{$this->talent->id}")->assertOk()
            ->assertJsonPath('data.contact', null)
            ->assertDontSee('manager@example.com');
    }

    public function test_the_admin_route_validates_the_same_rules_as_the_organizer_route(): void
    {
        $this->actingAs($this->admin, 'api')
            ->putJson("/api/admin/talents/{$this->talent->id}", ['type' => 'solo', 'contact_email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'contact_email']);
    }
}
