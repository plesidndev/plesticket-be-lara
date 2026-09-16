<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrganizerMember;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TicketScanRoleTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private TicketType $ticketType;

    private User $owner;

    // Built through the models rather than the API on purpose: actingAs() would
    // leave the platform `api` guard resolved for the rest of the test, and
    // RoleMiddleware reads that guard before the organizer one — which would
    // mask the very authorization this test exercises.
    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['is_organizer' => true, 'is_plesconnect_user' => true]);
        $this->event = Event::create([
            'event_id' => 'EVT9100', 'user_id' => $this->owner->id, 'title' => 'Scan Role Event',
            'slug' => 'scan-role-event', 'pic_name' => 'Owner', 'pic_identity_type' => 'ktp',
            'pic_identity_number' => '3200000000000001', 'start_date' => now()->addDays(10)->toDateString(),
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
        return $this->postJson('/api/organizer-auth/login', [
            'uid' => $uid, 'password' => 'password123',
        ])->assertOk()->json('data.token');
    }

    public function test_a_gate_officer_can_scan(): void
    {
        $this->member('GATE_OFFICER', 'EVT-GTE-0001');
        $this->ticket('SCANOK01');

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('EVT-GTE-0001'))
            ->postJson('/api/tickets/SCANOK01/scan')->assertOk()
            ->assertJsonPath('data.status', 'used')
            ->assertJsonPath('data.holder_name', 'Budi Santoso');
    }

    #[DataProvider('nonGateRoles')]
    public function test_other_organizer_roles_cannot_mark_a_ticket_used(string $role, string $uid): void
    {
        $this->member($role, $uid);
        $ticket = $this->ticket('SCAN'.substr(md5($role), 0, 4));

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($uid))
            ->postJson('/api/tickets/'.$ticket->ticket_code.'/scan')
            ->assertForbidden()
            ->assertJsonPath('message', 'Insufficient permissions.');

        // The ticket must still be usable by the gate afterwards.
        $this->assertSame('active', $ticket->fresh()->status->value);
    }

    public static function nonGateRoles(): array
    {
        return [
            'eo staff'         => ['EO_STAFF', 'EVT-STF-0001'],
            'mitra ticket box' => ['MITRA_TICKET_BOX', 'EVT-AGT-0001'],
            'band'             => ['BAND', 'EVT-BND-0001'],
            'media'            => ['MEDIA', 'EVT-MDA-0001'],
            'sponsor'          => ['SPONSOR', 'EVT-SPN-0001'],
        ];
    }

    public function test_an_unauthenticated_scan_is_rejected(): void
    {
        $this->ticket('SCANANON');

        $this->postJson('/api/tickets/SCANANON/scan')->assertUnauthorized();
    }
}
