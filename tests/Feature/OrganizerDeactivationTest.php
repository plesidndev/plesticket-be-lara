<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrganizerMember;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Crew tokens are minted with the long organizer TTL, so deactivating a member
 * has to take effect on their next request rather than whenever the JWT
 * expires. These cover the already-issued-token case specifically.
 */
class OrganizerDeactivationTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private TicketType $ticketType;

    private User $owner;

    // Built through the models rather than the API for the same reason as
    // TicketScanRoleTest: actingAs() would leave the platform `api` guard
    // resolved and mask the organizer-guard authorization under test.
    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['is_organizer' => true, 'is_plesconnect_user' => true]);
        $this->event = Event::create([
            'event_id' => 'EVT9200', 'user_id' => $this->owner->id, 'title' => 'Deactivation Event',
            'slug' => 'deactivation-event', 'pic_name' => 'Owner', 'pic_identity_type' => 'ktp',
            'pic_identity_number' => '3200000000000002', 'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(11)->toDateString(), 'verification_status' => 'verified',
        ]);
        $this->ticketType = TicketType::create([
            'event_id' => $this->event->id, 'name' => 'General', 'price' => 10000, 'quota' => 100,
        ]);
    }

    private function member(string $role, string $uid): OrganizerMember
    {
        return OrganizerMember::create([
            'uid' => $uid, 'owner_id' => $this->owner->id, 'event_id' => $this->event->id,
            'name' => 'Member '.$role, 'password' => 'password123', 'role' => $role,
            'commission_rate' => 0, 'is_active' => true,
        ]);
    }

    private function ticket(string $code): Ticket
    {
        $order = Order::create([
            'order_number' => 'ORD'.$code, 'event_id' => $this->event->id, 'buyer_name' => 'Buyer',
            'status' => 'paid', 'total_price' => 10000, 'paid_at' => now(),
        ]);
        $item = $order->items()->create([
            'ticket_type_id' => $this->ticketType->id, 'ticket_type_name' => 'General',
            'unit_price' => 10000, 'quantity' => 1, 'subtotal' => 10000,
        ]);

        return Ticket::create([
            'order_id' => $order->id, 'order_item_id' => $item->id, 'event_id' => $this->event->id,
            'ticket_type_id' => $this->ticketType->id,
            'ticket_code' => $code, 'holder_name' => 'Budi Santoso', 'status' => 'active',
        ]);
    }

    private function tokenFor(string $uid): string
    {
        $token = $this->postJson('/api/organizer-auth/login', [
            'uid' => $uid, 'password' => 'password123',
        ])->assertOk()->json('data.token');

        // Logging in leaves the member resolved on the organizer guard, and the
        // container outlives the request inside a single test. Production
        // serves each request in a fresh process, so drop the guard to make the
        // next call re-read the member from the database the way it really does.
        $this->app['auth']->forgetGuards();

        return $token;
    }

    public function test_deactivating_a_gate_officer_stops_an_already_issued_token(): void
    {
        $member = $this->member('GATE_OFFICER', 'EVT-GTE-0001');
        $ticket = $this->ticket('DEACT001');
        $token  = $this->tokenFor('EVT-GTE-0001');

        $member->update(['is_active' => false]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tickets/DEACT001/scan')
            ->assertForbidden()
            ->assertJsonPath('message', 'This account is inactive.')
            ->assertJsonPath('errors.code', 'ORGANIZER_MEMBER_INACTIVE');

        $this->assertSame('active', $ticket->fresh()->status->value);
    }

    public function test_an_active_gate_officer_is_unaffected(): void
    {
        $this->member('GATE_OFFICER', 'EVT-GTE-0002');
        $this->ticket('DEACT002');

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('EVT-GTE-0002'))
            ->postJson('/api/tickets/DEACT002/scan')
            ->assertOk()
            ->assertJsonPath('data.status', 'used');
    }

    public function test_a_deactivated_member_cannot_read_their_profile(): void
    {
        $member = $this->member('GATE_OFFICER', 'EVT-GTE-0003');
        $token  = $this->tokenFor('EVT-GTE-0003');

        $member->update(['is_active' => false]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/organizer-auth/me')
            ->assertForbidden()
            ->assertJsonPath('errors.code', 'ORGANIZER_MEMBER_INACTIVE');
    }

    public function test_a_deactivated_agent_loses_the_agent_portal(): void
    {
        $member = $this->member('MITRA_TICKET_BOX', 'EVT-AGT-0001');
        $token  = $this->tokenFor('EVT-AGT-0001');

        $member->update(['is_active' => false]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/agent/event')
            ->assertForbidden()
            ->assertJsonPath('errors.code', 'ORGANIZER_MEMBER_INACTIVE');
    }

    public function test_a_deactivated_member_can_still_log_out(): void
    {
        $member = $this->member('GATE_OFFICER', 'EVT-GTE-0004');
        $token  = $this->tokenFor('EVT-GTE-0004');

        $member->update(['is_active' => false]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/organizer-auth/logout')
            ->assertOk();
    }

    public function test_a_deleted_member_is_unauthenticated(): void
    {
        $member = $this->member('GATE_OFFICER', 'EVT-GTE-0005');
        $this->ticket('DEACT005');
        $token = $this->tokenFor('EVT-GTE-0005');

        $member->delete();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tickets/DEACT005/scan')
            ->assertUnauthorized();
    }

    public function test_login_is_still_refused_outright_when_inactive(): void
    {
        $member = $this->member('GATE_OFFICER', 'EVT-GTE-0006');
        $member->update(['is_active' => false]);

        $this->postJson('/api/organizer-auth/login', [
            'uid' => 'EVT-GTE-0006', 'password' => 'password123',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'This account is inactive.');
    }
}
