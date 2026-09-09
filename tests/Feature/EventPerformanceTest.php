<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $buyer;

    private Event $event;

    private TicketType $presale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['uid' => 'SA0001', 'name' => 'Super Admin', 'email' => 'admin@example.com', 'password' => 'secret', 'role' => 'SUPER_ADMIN']);
        // Flagged as an organizer: they own the event below, and the EO routes require it.
        $this->buyer = User::create(['uid' => 'U000001', 'name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER', 'is_organizer' => true]);
        $this->event = Event::create([
            'event_id' => 'EVT0001', 'user_id' => $this->buyer->id, 'title' => 'Konser Senja', 'slug' => 'konser-senja',
            'pic_name' => 'Panitia', 'pic_identity_type' => 'ktp', 'pic_identity_number' => '3200000000000001',
            'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->toDateString(),
            'verification_status' => 'verified',
        ]);

        // Capacity 100. Two paid (2 seats) and one pending (1 seat) still hold quota, so it reads 97.
        $this->presale = TicketType::create(['event_id' => $this->event->id, 'name' => 'Presale', 'price' => 150000, 'quota' => 97]);
    }

    private function order(string $number, OrderStatus $status, int $qty): Order
    {
        $order = Order::create([
            'order_number' => $number, 'buyer_id' => $this->buyer->id, 'buyer_name' => 'Budi',
            'event_id' => $this->event->id, 'status' => $status, 'total_price' => 150000 * $qty,
            'paid_at' => $status === OrderStatus::Paid ? now() : null,
        ]);
        $order->items()->create([
            'ticket_type_id' => $this->presale->id, 'ticket_type_name' => 'Presale',
            'unit_price' => 150000, 'quantity' => $qty, 'subtotal' => 150000 * $qty,
        ]);

        return $order->fresh();
    }

    public function test_it_derives_capacity_from_remaining_quota_plus_seats_still_held(): void
    {
        $this->order('ORD0001', OrderStatus::Paid, 2);
        $this->order('ORD0002', OrderStatus::PendingPayment, 1);

        // Cancelled and expired orders already returned their quota, so they must not inflate capacity.
        $this->order('ORD0003', OrderStatus::Cancelled, 5);
        $this->order('ORD0004', OrderStatus::Expired, 4);

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/events/'.$this->event->id.'/performance')->assertOk()
            ->assertJsonPath('data.ticket_types.0.remaining', 97)
            ->assertJsonPath('data.ticket_types.0.sold', 2)
            ->assertJsonPath('data.ticket_types.0.held', 1)
            ->assertJsonPath('data.ticket_types.0.capacity', 100)
            ->assertJsonPath('data.ticket_types.0.revenue', 300000)
            ->assertJsonPath('data.totals.capacity', 100)
            ->assertJsonPath('data.totals.sold', 2);
    }

    public function test_it_counts_orders_by_status_and_only_bills_paid_revenue(): void
    {
        $this->order('ORD0001', OrderStatus::Paid, 2);
        $this->order('ORD0002', OrderStatus::PendingPayment, 1);
        $this->order('ORD0003', OrderStatus::Cancelled, 5);

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/events/'.$this->event->id.'/performance')->assertOk()
            ->assertJsonPath('data.totals.orders_paid', 1)
            ->assertJsonPath('data.totals.orders_pending', 1)
            ->assertJsonPath('data.totals.orders_cancelled', 1)
            ->assertJsonPath('data.totals.orders_expired', 0)
            ->assertJsonPath('data.totals.gross_revenue', 300000);
    }

    public function test_it_reports_the_check_in_rate(): void
    {
        $order = $this->order('ORD0001', OrderStatus::Paid, 2);
        $item = $order->items->first();

        foreach ([['TIX-1', now()], ['TIX-2', null], ['TIX-3', null], ['TIX-4', null]] as [$code, $scannedAt]) {
            Ticket::create([
                'ticket_code' => $code, 'order_id' => $order->id, 'order_item_id' => $item->id,
                'ticket_type_id' => $this->presale->id, 'event_id' => $this->event->id, 'buyer_id' => $this->buyer->id,
                'holder_name' => 'Budi', 'status' => TicketStatus::Active, 'scanned_at' => $scannedAt,
            ]);
        }

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/events/'.$this->event->id.'/performance')->assertOk()
            ->assertJsonPath('data.totals.tickets_issued', 4)
            ->assertJsonPath('data.totals.tickets_scanned', 1)
            // JSON carries 25.0 as 25; the point is the value, not the float marker.
            ->assertJsonPath('data.totals.check_in_rate', 25);
    }

    public function test_it_keeps_one_decimal_on_an_uneven_check_in_rate(): void
    {
        $order = $this->order('ORD0001', OrderStatus::Paid, 2);
        $item = $order->items->first();

        foreach (range(1, 3) as $n) {
            Ticket::create([
                'ticket_code' => 'TIX-'.$n, 'order_id' => $order->id, 'order_item_id' => $item->id,
                'ticket_type_id' => $this->presale->id, 'event_id' => $this->event->id, 'buyer_id' => $this->buyer->id,
                'holder_name' => 'Budi', 'status' => TicketStatus::Active, 'scanned_at' => $n === 1 ? now() : null,
            ]);
        }

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/events/'.$this->event->id.'/performance')->assertOk()
            ->assertJsonPath('data.totals.check_in_rate', 33.3);
    }

    public function test_it_reports_a_zero_check_in_rate_rather_than_dividing_by_zero(): void
    {
        $this->actingAs($this->admin, 'api')->getJson('/api/admin/events/'.$this->event->id.'/performance')->assertOk()
            ->assertJsonPath('data.totals.tickets_issued', 0)
            ->assertJsonPath('data.totals.check_in_rate', 0);
    }

    public function test_it_counts_refunds_flagged_against_this_event_only(): void
    {
        $order = $this->order('ORD0001', OrderStatus::Paid, 2);
        Payment::create([
            'order_id' => $order->id, 'reference_id' => 'REF-001', 'provider' => PaymentProvider::Xendit,
            'method_code' => 'qris', 'type' => PaymentType::Qris, 'status' => PaymentStatus::Paid,
            'amount' => 300000, 'requires_refund' => true, 'paid_at' => now(),
        ]);

        $other = Event::create([
            'event_id' => 'EVT0002', 'user_id' => $this->buyer->id, 'title' => 'Other', 'slug' => 'other',
            'pic_name' => 'P', 'pic_identity_type' => 'ktp', 'pic_identity_number' => '3200000000000002',
            'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->toDateString(),
            'verification_status' => 'verified',
        ]);
        $otherOrder = Order::create([
            'order_number' => 'ORD9999', 'buyer_id' => $this->buyer->id, 'buyer_name' => 'Budi',
            'event_id' => $other->id, 'status' => OrderStatus::Paid, 'total_price' => 1, 'paid_at' => now(),
        ]);
        Payment::create([
            'order_id' => $otherOrder->id, 'reference_id' => 'REF-999', 'provider' => PaymentProvider::Xendit,
            'method_code' => 'qris', 'type' => PaymentType::Qris, 'status' => PaymentStatus::Paid,
            'amount' => 1, 'requires_refund' => true, 'paid_at' => now(),
        ]);

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/events/'.$this->event->id.'/performance')->assertOk()
            ->assertJsonPath('data.totals.refunds_flagged', 1);
    }

    public function test_it_lists_a_ticket_type_that_has_sold_nothing(): void
    {
        TicketType::create(['event_id' => $this->event->id, 'name' => 'On the day', 'price' => 200000, 'quota' => 50]);

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/events/'.$this->event->id.'/performance')->assertOk()
            ->assertJsonCount(2, 'data.ticket_types')
            ->assertJsonPath('data.ticket_types.1.name', 'On the day')
            ->assertJsonPath('data.ticket_types.1.sold', 0)
            ->assertJsonPath('data.ticket_types.1.capacity', 50);
    }

    public function test_it_404s_for_an_unknown_event_and_denies_admins_without_events_view(): void
    {
        $this->actingAs($this->admin, 'api')->getJson('/api/admin/events/00000000-0000-0000-0000-000000000000/performance')->assertNotFound();

        $limited = User::create(['uid' => 'AD0002', 'name' => 'Limited', 'email' => 'limited@example.com', 'password' => 'secret', 'role' => 'ADMIN']);
        $limited->permissions()->create(['permission' => 'console.access']);

        $this->actingAs($limited, 'api')->getJson('/api/admin/events/'.$this->event->id.'/performance')->assertForbidden();
    }

    public function test_an_organizer_sees_the_same_figures_for_their_own_event(): void
    {
        $this->order('ORD0001', OrderStatus::Paid, 2);

        // The event above belongs to $this->buyer, who is its organizer.
        $this->actingAs($this->buyer, 'api')->getJson('/api/events/'.$this->event->id.'/performance')->assertOk()
            ->assertJsonPath('data.totals.sold', 2)
            ->assertJsonPath('data.totals.gross_revenue', 300000);
    }

    public function test_a_non_organizer_is_refused_by_the_eo_gate(): void
    {
        $plain = User::create(['uid' => 'U000008', 'name' => 'Plain', 'email' => 'plain@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER']);

        $this->actingAs($plain, 'api')->getJson('/api/events/'.$this->event->id.'/performance')->assertForbidden();
    }

    public function test_an_organizer_cannot_read_another_organizers_event(): void
    {
        // An organizer in good standing, so they clear the eo gate but still do not own this event.
        $rival = User::create(['uid' => 'U000009', 'name' => 'Rival', 'email' => 'rival@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER', 'is_organizer' => true]);

        $this->actingAs($rival, 'api')->getJson('/api/events/'.$this->event->id.'/performance')->assertNotFound();
    }
}
