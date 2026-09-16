<?php

namespace Tests\Feature;

use App\Models\OrganizerMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class OrganizerAuthTtlTest extends TestCase
{
    use RefreshDatabase;

    private function createGateOfficer(): OrganizerMember
    {
        $owner = User::factory()->create(['is_organizer' => true, 'is_plesconnect_user' => true]);
        $event = $this->actingAs($owner->fresh(), 'api')->postJson('/api/events', [
            'title' => 'Gate TTL Event', 'pic_name' => 'Owner', 'pic_identity_type' => 'ktp',
            'pic_identity_number' => '001234', 'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(11)->toDateString(),
            'ticket_types' => [['name' => 'General', 'price' => 10000, 'quota' => 100]],
        ])->assertCreated()->json('data');

        return OrganizerMember::create([
            'uid' => 'EVT-GTE-0001', 'owner_id' => $owner->id, 'event_id' => $event['id'],
            'name' => 'Gate One', 'password' => 'password123', 'role' => 'GATE_OFFICER',
            'commission_rate' => 0, 'is_active' => true,
        ]);
    }

    public function test_organizer_token_uses_the_crew_ttl_not_the_global_one(): void
    {
        config(['auth.guards.organizer.ttl' => 720, 'jwt.ttl' => 60]);
        $this->createGateOfficer();

        $token = $this->postJson('/api/organizer-auth/login', [
            'uid' => 'EVT-GTE-0001', 'password' => 'password123',
        ])->assertOk()->json('data.token');

        $claims = JWTAuth::setToken($token)->getPayload();
        $lifetimeMinutes = (int) round(($claims->get('exp') - $claims->get('iat')) / 60);

        $this->assertSame(720, $lifetimeMinutes);
    }

    public function test_login_response_carries_the_event_id_the_crew_app_renders(): void
    {
        $member = $this->createGateOfficer();

        $this->postJson('/api/organizer-auth/login', [
            'uid' => 'EVT-GTE-0001', 'password' => 'password123',
        ])->assertOk()
            ->assertJsonPath('data.member.event_id', $member->event_id)
            ->assertJsonPath('data.member.uid', 'EVT-GTE-0001')
            ->assertJsonPath('data.member.role', 'GATE_OFFICER')
            ->assertJsonPath('data.member.role_label', 'Gate Officer');
    }

    public function test_me_endpoint_rehydrates_the_member_after_an_app_restart(): void
    {
        $member = $this->createGateOfficer();

        $token = $this->postJson('/api/organizer-auth/login', [
            'uid' => 'EVT-GTE-0001', 'password' => 'password123',
        ])->assertOk()->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/organizer-auth/me')->assertOk()
            ->assertJsonPath('data.event_id', $member->event_id)
            ->assertJsonPath('data.name', 'Gate One');
    }
}
