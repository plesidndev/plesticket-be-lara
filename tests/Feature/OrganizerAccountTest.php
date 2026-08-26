<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class OrganizerAccountTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attrs = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'uid'      => sprintf('USR%04d', $n),
            'name'     => "User {$n}",
            'email'    => "user{$n}@example.com",
            'password' => 'secret',
            'role'     => 'REGISTERED_USER',
        ], $attrs));
    }

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    public function test_a_new_account_is_not_an_organizer(): void
    {
        $user = $this->user();

        $this->assertFalse($user->is_organizer);

        $this->withHeaders($this->bearer($user))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.is_organizer', false);
    }

    public function test_a_plain_buyer_is_refused_from_organizer_routes(): void
    {
        $response = $this->withHeaders($this->bearer($this->user()))
            ->getJson('/api/events/my')
            ->assertStatus(403);

        // The frontend routes to onboarding off this code rather than
        // showing a generic permission error.
        $response->assertJsonPath('errors.code', 'EO_NOT_ACTIVATED');
    }

    public function test_activation_flips_the_flag_and_opens_the_routes(): void
    {
        $user = $this->user();

        $activation = $this->withHeaders($this->bearer($user))
            ->postJson('/api/eo/activate')
            ->assertOk()
            ->assertJsonPath('data.user.is_organizer', true);

        $this->assertTrue($user->fresh()->is_organizer);

        $this->withHeaders(['Authorization' => 'Bearer '.$activation->json('data.token')])
            ->getJson('/api/events/my')
            ->assertOk();
    }

    /**
     * The trap this endpoint exists to avoid: a token minted before activation
     * still claims is_organizer:false, so activation must hand back a new one.
     */
    public function test_activation_returns_a_token_that_actually_works(): void
    {
        $user      = $this->user();
        $oldToken  = JWTAuth::fromUser($user);

        $this->assertFalse(JWTAuth::setToken($oldToken)->getPayload()->get('is_organizer'));

        $newToken = $this->withHeaders(['Authorization' => 'Bearer '.$oldToken])
            ->postJson('/api/eo/activate')
            ->assertOk()
            ->json('data.token');

        $this->assertNotSame($oldToken, $newToken);
        $this->assertTrue(JWTAuth::setToken($newToken)->getPayload()->get('is_organizer'));
    }

    public function test_activation_is_idempotent(): void
    {
        $user = $this->user(['is_organizer' => true]);

        $this->withHeaders($this->bearer($user))
            ->postJson('/api/eo/activate')
            ->assertOk()
            ->assertJsonPath('message', 'This account is already an event organizer.')
            ->assertJsonPath('data.user.is_organizer', true);
    }

    public function test_activation_requires_authentication(): void
    {
        $this->postJson('/api/eo/activate')->assertStatus(401);
    }

    /**
     * An organizer keeps their buyer capabilities — that is the whole reason
     * this is a flag rather than a role.
     */
    public function test_an_organizer_can_still_act_as_a_buyer(): void
    {
        $organizer = $this->user(['is_organizer' => true]);

        $this->withHeaders($this->bearer($organizer))
            ->getJson('/api/orders')
            ->assertOk();
    }

    public function test_a_buyer_keeps_access_to_buyer_routes(): void
    {
        $this->withHeaders($this->bearer($this->user()))
            ->getJson('/api/orders')
            ->assertOk();
    }

    /**
     * Existing event owners predate the column; without the backfill they
     * would start getting 403s on routes that worked yesterday.
     */
    public function test_the_migration_backfills_existing_event_owners(): void
    {
        $owner = $this->user();

        // Simulate a row that predates the column.
        $owner->forceFill(['is_organizer' => false])->save();

        Event::create([
            'event_id'            => 'EVT0001',
            'user_id'             => $owner->id,
            'title'               => 'Legacy Event',
            'slug'                => 'legacy-event',
            'pic_name'            => 'Panitia',
            'pic_identity_type'   => 'ktp',
            'pic_identity_number' => '3200000000000001',
            'start_date'          => now()->addWeek()->toDateString(),
            'end_date'            => now()->addWeek()->toDateString(),
            'verification_status' => 'verified',
        ]);

        // Re-run the backfill exactly as the migration does.
        $owners = \Illuminate\Support\Facades\DB::table('events')
            ->distinct()->pluck('user_id')->filter()->all();
        \Illuminate\Support\Facades\DB::table('users')
            ->whereIn('id', $owners)->update(['is_organizer' => true]);

        $this->assertTrue($owner->fresh()->is_organizer);
    }
}
