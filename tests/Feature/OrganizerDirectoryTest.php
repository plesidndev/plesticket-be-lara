<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['uid' => 'SA0001', 'name' => 'Super Admin', 'email' => 'admin@example.com', 'password' => 'secret', 'role' => 'SUPER_ADMIN']);
    }

    private function organizer(string $uid, string $name, string $email, int $events = 0, bool $active = true): User
    {
        $user = User::create(['uid' => $uid, 'name' => $name, 'email' => $email, 'password' => 'secret',
            'role' => 'REGISTERED_USER', 'is_organizer' => true, 'is_active' => $active]);

        foreach (range(1, max(0, $events)) as $n) {
            if ($events === 0) {
                break;
            }
            Event::create([
                'event_id' => strtoupper(substr($uid, -3)).$n, 'user_id' => $user->id, 'title' => "Event {$n}", 'slug' => strtolower($uid)."-{$n}",
                'pic_name' => 'P', 'pic_identity_type' => 'ktp', 'pic_identity_number' => '320000000000000'.$n,
                'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->toDateString(),
                'verification_status' => 'verified',
            ]);
        }

        return $user;
    }

    public function test_it_lists_only_accounts_flagged_as_organizers_with_event_counts(): void
    {
        $this->organizer('U000001', 'Andi', 'andi@example.com', events: 2);
        $this->organizer('U000002', 'Budi', 'budi@example.com');
        // A plain member must not appear: is_organizer is a flag, not a role.
        User::create(['uid' => 'U000003', 'name' => 'Citra', 'email' => 'citra@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER']);

        $response = $this->actingAs($this->admin, 'api')->getJson('/api/organizers')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Andi')
            ->assertJsonPath('data.0.events_count', 2)
            ->assertJsonPath('data.1.name', 'Budi')
            ->assertJsonPath('data.1.events_count', 0)
            ->assertJsonPath('meta.total', 2);

        $this->assertStringNotContainsString('Citra', $response->getContent());
    }

    public function test_it_searches_and_filters_by_active_state(): void
    {
        $this->organizer('U000001', 'Andi', 'andi@example.com');
        $this->organizer('U000002', 'Budi', 'budi@example.com', active: false);

        $this->actingAs($this->admin, 'api')->getJson('/api/organizers?search=budi')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Budi');

        $this->actingAs($this->admin, 'api')->getJson('/api/organizers?is_active=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Andi');
    }

    public function test_it_paginates(): void
    {
        foreach (range(1, 3) as $n) {
            $this->organizer('U00000'.$n, 'Organizer '.$n, "eo{$n}@example.com");
        }

        $this->actingAs($this->admin, 'api')->getJson('/api/organizers?limit=2&page=2')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 3)->assertJsonPath('meta.pages', 2);
    }

    public function test_it_requires_the_organizers_view_grant(): void
    {
        $limited = User::create(['uid' => 'AD0002', 'name' => 'Limited', 'email' => 'limited@example.com', 'password' => 'secret', 'role' => 'ADMIN']);
        $limited->permissions()->create(['permission' => 'console.access']);

        $this->actingAs($limited, 'api')->getJson('/api/organizers')->assertForbidden();

        // users.view is a different grant and must not stand in for it.
        $limited->permissions()->create(['permission' => 'users.view']);
        $this->actingAs($limited->fresh(), 'api')->getJson('/api/organizers')->assertForbidden();

        $limited->permissions()->create(['permission' => 'organizers.view']);
        $this->actingAs($limited->fresh(), 'api')->getJson('/api/organizers')->assertOk();
    }
}
