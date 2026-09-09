<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\WebhookDeliveryStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TicketType;
use App\Models\WebhookDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminOrderConsoleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $buyer;

    private Event $event;

    private TicketType $ticketType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['uid' => 'SA0001', 'name' => 'Super Admin', 'email' => 'admin@example.com', 'password' => 'secret', 'role' => 'SUPER_ADMIN']);
        $this->buyer = User::create(['uid' => 'U000001', 'name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER']);
        $this->event = Event::create([
            'event_id' => 'EVT0001', 'user_id' => $this->buyer->id, 'title' => 'Konser Senja', 'slug' => 'konser-senja',
            'pic_name' => 'Panitia', 'pic_identity_type' => 'ktp', 'pic_identity_number' => '3200000000000001',
            'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->toDateString(),
            'verification_status' => 'verified',
        ]);
        // 100 seats less the 2 held by the pending order built below.
        $this->ticketType = TicketType::create(['event_id' => $this->event->id, 'name' => 'Presale', 'price' => 150000, 'quota' => 98]);
    }

    private function order(string $number, OrderStatus $status, array $attrs = []): Order
    {
        $order = Order::create(array_merge([
            'order_number' => $number, 'buyer_id' => $this->buyer->id, 'buyer_name' => 'Budi', 'buyer_phone' => '08111',
            'event_id' => $this->event->id, 'status' => $status, 'total_price' => 300000, 'payment_method' => 'qris',
        ], $attrs));

        $order->items()->create([
            'ticket_type_id' => $this->ticketType->id, 'ticket_type_name' => 'Presale',
            'unit_price' => 150000, 'quantity' => 2, 'subtotal' => 300000,
        ]);

        return $order->fresh();
    }

    public function test_it_lists_transactions_with_buyer_and_event_context(): void
    {
        $this->order('ORD0001', OrderStatus::Paid, ['paid_at' => now()]);

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/orders')->assertOk()
            ->assertJsonPath('data.0.order_number', 'ORD0001')
            ->assertJsonPath('data.0.event_title', 'Konser Senja')
            ->assertJsonPath('data.0.buyer_email', 'budi@example.com')
            ->assertJsonPath('data.0.item_count', 1)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_it_filters_by_status_search_and_refund_flag(): void
    {
        $this->order('ORD0001', OrderStatus::Paid, ['paid_at' => now()]);
        $pending = $this->order('ORD0002', OrderStatus::PendingPayment, ['buyer_name' => 'Siti']);
        Payment::create([
            'order_id' => $pending->id, 'reference_id' => 'REF-002', 'provider' => PaymentProvider::Xendit,
            'method_code' => 'qris', 'type' => PaymentType::Qris, 'status' => PaymentStatus::Paid,
            'amount' => 300000, 'requires_refund' => true, 'paid_at' => now(),
        ]);

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/orders?status=paid')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.order_number', 'ORD0001');

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/orders?search=Siti')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.order_number', 'ORD0002');

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/orders?requires_refund=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.order_number', 'ORD0002');
    }

    public function test_it_shows_one_transaction_with_payments_and_items(): void
    {
        $order = $this->order('ORD0001', OrderStatus::Paid, ['paid_at' => now()]);
        Payment::create([
            'order_id' => $order->id, 'reference_id' => 'REF-001', 'provider' => PaymentProvider::Xendit,
            'method_code' => 'qris', 'type' => PaymentType::Qris, 'status' => PaymentStatus::Paid, 'amount' => 300000, 'paid_at' => now(),
        ]);

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/orders/ORD0001')->assertOk()
            ->assertJsonPath('data.payments.0.reference_id', 'REF-001')
            ->assertJsonPath('data.items.0.ticket_type_name', 'Presale')
            ->assertJsonPath('data.items.0.quantity', 2);

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/orders/ORD-NOPE')->assertNotFound();
    }

    public function test_it_cancels_a_pending_order_and_returns_its_quota(): void
    {
        $this->order('ORD0002', OrderStatus::PendingPayment);

        $this->actingAs($this->admin, 'api')->postJson('/api/admin/orders/ORD0002/cancel')
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(100, $this->ticketType->fresh()->quota);
    }

    public function test_it_refuses_to_cancel_an_order_that_is_not_pending(): void
    {
        $this->order('ORD0001', OrderStatus::Paid, ['paid_at' => now()]);

        $this->actingAs($this->admin, 'api')->postJson('/api/admin/orders/ORD0001/cancel')
            ->assertStatus(422)->assertJsonPath('errors.order.0', 'Only pending orders can be cancelled.');

        $this->assertSame(OrderStatus::Paid, Order::where('order_number', 'ORD0001')->firstOrFail()->status);
        $this->assertSame(98, $this->ticketType->fresh()->quota);
    }

    public function test_it_settles_a_flagged_refund_and_drains_the_queue(): void
    {
        $order = $this->order('ORD0001', OrderStatus::Paid, ['paid_at' => now()]);
        Payment::create([
            'order_id' => $order->id, 'reference_id' => 'REF-001', 'provider' => PaymentProvider::Xendit,
            'method_code' => 'qris', 'type' => PaymentType::Qris, 'status' => PaymentStatus::Paid,
            'amount' => 300000, 'requires_refund' => true, 'paid_at' => now(),
        ]);

        $this->assertSame(1, Payment::where('requires_refund', true)->count());

        $this->actingAs($this->admin, 'api')->postJson('/api/admin/orders/ORD0001/settle-refund')
            ->assertOk()->assertJsonPath('data.payments.0.requires_refund', false);

        $this->assertSame(0, Payment::where('requires_refund', true)->count());
    }

    public function test_it_refuses_to_settle_an_order_with_no_flagged_refund(): void
    {
        $this->order('ORD0001', OrderStatus::Paid, ['paid_at' => now()]);

        $this->actingAs($this->admin, 'api')->postJson('/api/admin/orders/ORD0001/settle-refund')->assertStatus(422);
    }

    public function test_it_denies_transactions_to_an_admin_without_the_grant(): void
    {
        $limited = User::create(['uid' => 'AD0002', 'name' => 'Limited', 'email' => 'limited@example.com', 'password' => 'secret', 'role' => 'ADMIN']);
        $limited->permissions()->create(['permission' => 'console.access']);

        $this->actingAs($limited, 'api')->getJson('/api/admin/orders')->assertForbidden();
        $this->actingAs($limited, 'api')->postJson('/api/admin/orders/ORD0001/cancel')->assertForbidden();
    }

    public function test_it_paginates_and_keeps_the_filter_on_later_pages(): void
    {
        foreach (range(1, 25) as $n) {
            $this->order(sprintf('ORD%04d', $n), $n % 2 === 0 ? OrderStatus::Paid : OrderStatus::PendingPayment);
        }

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/orders?limit=10&page=2')->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.limit', 10)
            ->assertJsonPath('meta.pages', 3);

        // The filter still applies on a later page rather than being dropped by the paginator.
        $this->actingAs($this->admin, 'api')->getJson('/api/admin/orders?status=paid&limit=5&page=2')->assertOk()
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.page', 2)
            ->assertJsonCount(5, 'data');
    }

    public function test_it_exposes_the_buyer_uid_and_filters_by_it(): void
    {
        $other = User::create(['uid' => 'U000002', 'name' => 'Siti', 'email' => 'siti@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER']);
        $this->order('ORD0001', OrderStatus::Paid, ['paid_at' => now()]);
        $this->order('ORD0002', OrderStatus::Paid, ['buyer_id' => $other->id, 'buyer_name' => 'Siti', 'paid_at' => now()]);

        // Newest first, so the second order leads.
        $this->actingAs($this->admin, 'api')->getJson('/api/admin/orders')->assertOk()
            ->assertJsonPath('data.0.buyer_uid', 'U000002')
            ->assertJsonPath('data.1.buyer_uid', 'U000001');

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/orders?buyer_uid=U000002')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.order_number', 'ORD0002');
    }

    public function test_it_reports_the_callbacks_recorded_against_an_order(): void
    {
        $order = $this->order('ORD0001', OrderStatus::Paid, ['paid_at' => now()]);
        Payment::create([
            'order_id' => $order->id, 'reference_id' => 'REF-001', 'provider' => PaymentProvider::Xendit,
            'method_code' => 'qris', 'type' => PaymentType::Qris, 'status' => PaymentStatus::Paid, 'amount' => 300000, 'paid_at' => now(),
        ]);

        WebhookDelivery::create([
            'id' => (string) Str::uuid(), 'provider' => PaymentProvider::Xendit, 'event_type' => 'payment.succeeded',
            'reference_id' => 'REF-001', 'status' => WebhookDeliveryStatus::Applied, 'payload' => ['ok' => true], 'processed_at' => now(),
        ]);
        WebhookDelivery::create([
            'id' => (string) Str::uuid(), 'provider' => PaymentProvider::Xendit, 'event_type' => 'payment.succeeded',
            'reference_id' => 'REF-001', 'status' => WebhookDeliveryStatus::Failed, 'payload' => ['ok' => false], 'error' => 'boom',
        ]);
        // Belongs to a different payment entirely and must not leak into this order.
        WebhookDelivery::create([
            'id' => (string) Str::uuid(), 'provider' => PaymentProvider::Xendit, 'event_type' => 'payment.succeeded',
            'reference_id' => 'REF-OTHER', 'status' => WebhookDeliveryStatus::Applied, 'payload' => [],
        ]);

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/orders/ORD0001')->assertOk()
            ->assertJsonCount(2, 'data.callbacks')
            ->assertJsonPath('data.callbacks.0.status', 'applied')
            ->assertJsonPath('data.callbacks.0.event_type', 'payment.succeeded')
            ->assertJsonPath('data.callbacks.1.status', 'failed')
            ->assertJsonPath('data.callbacks.1.error', 'boom');
    }

    public function test_it_reports_no_callbacks_when_none_arrived(): void
    {
        $order = $this->order('ORD0002', OrderStatus::PendingPayment);
        Payment::create([
            'order_id' => $order->id, 'reference_id' => 'REF-002', 'provider' => PaymentProvider::Xendit,
            'method_code' => 'qris', 'type' => PaymentType::Qris, 'status' => PaymentStatus::Pending, 'amount' => 300000,
        ]);

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/orders/ORD0002')->assertOk()
            ->assertJsonCount(0, 'data.callbacks');
    }
}
