<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /tickets/{code} used to return any ticket to any authenticated account, exposing another
 * customer's holder name and order number to anyone who could guess a code.
 */
class TicketLookupScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $stranger;

    private User $organizer;

    private User $otherOrganizer;

    private User $admin;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = $this->member('U000001', 'Budi', 'budi@example.com');
        $this->stranger = $this->member('U000002', 'Siti', 'siti@example.com');
        $this->organizer = $this->member('U000003', 'Organizer', 'eo@example.com');
        $this->otherOrganizer = $this->member('U000004', 'Rival', 'rival@example.com');
        $this->admin = User::create(['uid' => 'SA0001', 'name' => 'Super Admin', 'email' => 'admin@example.com', 'password' => 'secret', 'role' => 'SUPER_ADMIN']);

        $this->ticket = $this->ticketFor($this->buyer);
    }

    private function member(string $uid, string $name, string $email): User
    {
        return User::create(['uid' => $uid, 'name' => $name, 'email' => $email, 'password' => 'secret', 'role' => 'REGISTERED_USER']);
    }

    private function ticketFor(?User $buyer, string $code = 'TIX-0001'): Ticket
    {
        $event = Event::firstOrCreate(
            ['event_id' => 'EVT0001'],
            [
                'user_id' => $this->organizer->id, 'title' => 'Konser Senja', 'slug' => 'konser-senja',
                'pic_name' => 'Panitia', 'pic_identity_type' => 'ktp', 'pic_identity_number' => '3200000000000001',
                'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->toDateString(),
                'verification_status' => 'verified',
            ]
        );
        $ticketType = TicketType::firstOrCreate(['event_id' => $event->id, 'name' => 'Presale'], ['price' => 150000, 'quota' => 100]);

        $order = Order::create([
            'order_number' => 'ORD'.$code, 'buyer_id' => $buyer?->id, 'buyer_name' => $buyer?->name ?? 'Walk-in',
            'event_id' => $event->id, 'status' => OrderStatus::Paid, 'total_price' => 150000, 'paid_at' => now(),
        ]);
        $item = $order->items()->create([
            'ticket_type_id' => $ticketType->id, 'ticket_type_name' => 'Presale',
            'unit_price' => 150000, 'quantity' => 1, 'subtotal' => 150000,
        ]);

        return Ticket::create([
            'ticket_code' => $code, 'order_id' => $order->id, 'order_item_id' => $item->id,
            'ticket_type_id' => $ticketType->id, 'event_id' => $event->id, 'buyer_id' => $buyer?->id,
            'holder_name' => $buyer?->name ?? 'Walk-in', 'status' => TicketStatus::Active,
        ]);
    }

    public function test_the_buyer_who_holds_the_ticket_can_read_it(): void
    {
        $this->actingAs($this->buyer, 'api')->getJson('/api/tickets/TIX-0001')->assertOk()
            ->assertJsonPath('data.ticket_code', 'TIX-0001')
            ->assertJsonPath('data.holder_name', 'Budi');
    }

    public function test_an_unrelated_buyer_cannot_read_someone_elses_ticket(): void
    {
        $this->actingAs($this->stranger, 'api')->getJson('/api/tickets/TIX-0001')
            ->assertNotFound()
            // Same body as a code that does not exist, so real codes cannot be probed.
            ->assertJsonPath('message', 'Ticket not found.');
    }

    public function test_an_unknown_code_is_indistinguishable_from_a_forbidden_one(): void
    {
        $forbidden = $this->actingAs($this->stranger, 'api')->getJson('/api/tickets/TIX-0001');
        $missing = $this->actingAs($this->stranger, 'api')->getJson('/api/tickets/TIX-NOPE');

        $this->assertSame($forbidden->status(), $missing->status());
        $this->assertSame($forbidden->json(), $missing->json());
    }

    public function test_the_organizer_of_the_event_can_read_it(): void
    {
        $this->actingAs($this->organizer, 'api')->getJson('/api/tickets/TIX-0001')->assertOk()
            ->assertJsonPath('data.ticket_code', 'TIX-0001');
    }

    public function test_an_organizer_of_a_different_event_cannot(): void
    {
        $this->actingAs($this->otherOrganizer, 'api')->getJson('/api/tickets/TIX-0001')->assertNotFound();
    }

    public function test_console_staff_can_read_any_ticket(): void
    {
        $this->actingAs($this->admin, 'api')->getJson('/api/tickets/TIX-0001')->assertOk()
            ->assertJsonPath('data.ticket_code', 'TIX-0001');

        $limited = User::create(['uid' => 'AD0002', 'name' => 'Limited', 'email' => 'limited@example.com', 'password' => 'secret', 'role' => 'ADMIN']);
        $limited->permissions()->create(['permission' => 'console.access']);

        $this->actingAs($limited, 'api')->getJson('/api/tickets/TIX-0001')->assertOk();
    }

    public function test_a_staff_account_without_console_access_is_treated_as_a_stranger(): void
    {
        $limited = User::create(['uid' => 'AD0003', 'name' => 'No Console', 'email' => 'nocon@example.com', 'password' => 'secret', 'role' => 'ADMIN']);

        $this->actingAs($limited, 'api')->getJson('/api/tickets/TIX-0001')->assertNotFound();
    }

    public function test_a_guest_sale_with_no_buyer_is_not_readable_by_a_null_matching_account(): void
    {
        $this->ticketFor(null, 'TIX-0002');

        // buyer_id is null here; a stranger must not slip through on a null comparison.
        $this->actingAs($this->stranger, 'api')->getJson('/api/tickets/TIX-0002')->assertNotFound();
        $this->actingAs($this->organizer, 'api')->getJson('/api/tickets/TIX-0002')->assertOk();
    }

    public function test_lookup_still_requires_authentication(): void
    {
        $this->getJson('/api/tickets/TIX-0001')->assertUnauthorized();
    }

    public function test_it_returns_the_context_a_lookup_screen_needs(): void
    {
        $this->actingAs($this->admin, 'api')->getJson('/api/tickets/TIX-0001')->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.event.title', 'Konser Senja')
            ->assertJsonPath('data.ticket_type.name', 'Presale')
            // order_number is whenLoaded; without the relation eager-loaded it silently disappears.
            ->assertJsonPath('data.order_number', 'ORDTIX-0001')
            ->assertJsonPath('data.scanned_at', null)
            ->assertJsonPath('data.scanned_by', null);
    }
}
