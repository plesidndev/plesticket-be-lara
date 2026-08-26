<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XenditWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-callback-token';

    private Order   $order;
    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.xendit.callback_token', self::TOKEN);
        config()->set('services.xendit.secret_key', 'xnd_test_key');

        $buyer = User::create([
            'uid'      => 'USR0001',
            'name'     => 'Budi Santoso',
            'email'    => 'budi@example.com',
            'password' => 'secret',
            'role'     => 'REGISTERED_USER',
        ]);

        $event = Event::create([
            'event_id'            => 'EVT0001',
            'user_id'             => $buyer->id,
            'title'               => 'Konser Senja',
            'slug'                => 'konser-senja',
            'pic_name'            => 'Panitia',
            'pic_identity_type'   => 'ktp',
            'pic_identity_number' => '3200000000000001',
            'start_date'          => now()->addWeek()->toDateString(),
            'end_date'            => now()->addWeek()->toDateString(),
            'verification_status' => 'verified',
        ]);

        $ticketType = TicketType::create([
            'event_id' => $event->id,
            'name'     => 'Presale',
            'price'    => 150000,
            // 100 seats, less the 2 this order holds — as OrderService::create
            // would have left it after decrementing.
            'quota'    => 98,
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD202608260001',
            'buyer_id'     => $buyer->id,
            'event_id'     => $event->id,
            'status'       => OrderStatus::PendingPayment,
            'total_price'  => 300000,
            'expires_at'   => now()->addMinutes(30),
        ]);

        $this->order->items()->create([
            'ticket_type_id'   => $ticketType->id,
            'ticket_type_name' => $ticketType->name,
            'unit_price'       => 150000,
            'quantity'         => 2,
            'subtotal'         => 300000,
        ]);

        $this->payment = Payment::create([
            'order_id'     => $this->order->id,
            'reference_id' => 'ORD202608260001-AB12CD',
            'provider'     => PaymentProvider::Xendit,
            'method_code'  => 'qris',
            'type'         => PaymentType::Qris,
            'status'       => PaymentStatus::Pending,
            'amount'       => 300000,
            'expires_at'   => now()->addMinutes(30),
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'event' => 'payment.succeeded',
            'data'  => array_merge([
                'id'                 => 'ps_test_1',
                'payment_request_id' => 'pr_test_1',
                'reference_id'       => $this->payment->reference_id,
                'amount'             => 300000,
                'status'             => 'SUCCEEDED',
                'created'            => now()->toIso8601String(),
            ], $overrides),
        ];
    }

    public function test_it_rejects_a_callback_with_a_bad_token(): void
    {
        $this->withHeader('x-callback-token', 'wrong-token')
            ->postJson('/api/webhooks/xendit', $this->payload())
            ->assertStatus(401);

        $this->assertSame(PaymentStatus::Pending, $this->payment->fresh()->status);
        $this->assertSame(OrderStatus::PendingPayment, $this->order->fresh()->status);
    }

    public function test_it_rejects_a_callback_with_no_token_at_all(): void
    {
        $this->postJson('/api/webhooks/xendit', $this->payload())->assertStatus(401);

        $this->assertSame(PaymentStatus::Pending, $this->payment->fresh()->status);
    }

    public function test_a_successful_callback_settles_the_order_and_issues_tickets(): void
    {
        $this->withHeader('x-callback-token', self::TOKEN)
            ->postJson('/api/webhooks/xendit', $this->payload())
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $payment = $this->payment->fresh();
        $order   = $this->order->fresh();

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('pr_test_1', $payment->provider_reference);

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('qris', $order->payment_method);
        $this->assertNotNull($order->paid_at);

        // Two tickets, matching the single order item's quantity.
        $this->assertSame(2, Ticket::where('order_id', $order->id)->count());
        $this->assertSame('Budi Santoso', Ticket::where('order_id', $order->id)->first()->holder_name);
    }

    public function test_a_redelivered_callback_does_not_issue_a_second_set_of_tickets(): void
    {
        $send = fn() => $this->withHeader('x-callback-token', self::TOKEN)
            ->postJson('/api/webhooks/xendit', $this->payload());

        $send()->assertOk();
        $send()->assertOk();
        $send()->assertOk();

        $this->assertSame(2, Ticket::where('order_id', $this->order->id)->count());
        $this->assertSame(OrderStatus::Paid, $this->order->fresh()->status);
    }

    public function test_an_underpayment_fails_the_payment_and_issues_no_tickets(): void
    {
        $this->withHeader('x-callback-token', self::TOKEN)
            ->postJson('/api/webhooks/xendit', $this->payload(['amount' => 150000]))
            ->assertOk();

        $this->assertSame(PaymentStatus::Failed, $this->payment->fresh()->status);
        $this->assertSame(OrderStatus::PendingPayment, $this->order->fresh()->status);
        $this->assertSame(0, Ticket::where('order_id', $this->order->id)->count());
    }

    public function test_an_expiry_callback_leaves_a_still_valid_order_open_for_retry(): void
    {
        // The order still has 30 minutes on its hold.
        $this->withHeader('x-callback-token', self::TOKEN)
            ->postJson('/api/webhooks/xendit', $this->payload(['status' => 'EXPIRED']))
            ->assertOk();

        $this->assertSame(PaymentStatus::Expired, $this->payment->fresh()->status);

        // The buyer can still pick another method, so the seats stay held.
        $this->assertSame(OrderStatus::PendingPayment, $this->order->fresh()->status);
        $this->assertSame(98, TicketType::first()->quota);
    }

    public function test_an_expiry_callback_releases_an_order_whose_hold_has_lapsed(): void
    {
        $this->order->update(['expires_at' => now()->subMinute()]);

        $this->withHeader('x-callback-token', self::TOKEN)
            ->postJson('/api/webhooks/xendit', $this->payload(['status' => 'EXPIRED']))
            ->assertOk();

        $this->assertSame(PaymentStatus::Expired, $this->payment->fresh()->status);
        $this->assertSame(OrderStatus::Expired, $this->order->fresh()->status);

        // The 2 held seats are returned to the pool.
        $this->assertSame(100, TicketType::first()->quota);
    }

    public function test_an_unknown_reference_is_acknowledged_without_side_effects(): void
    {
        $this->withHeader('x-callback-token', self::TOKEN)
            ->postJson('/api/webhooks/xendit', $this->payload(['reference_id' => 'ORD-DOES-NOT-EXIST']))
            ->assertOk();

        $this->assertSame(PaymentStatus::Pending, $this->payment->fresh()->status);
    }

    /**
     * Callbacks already in flight when the gateway moved to the Payment
     * Requests API still carry the old QR Codes shape; they must keep working.
     */
    public function test_a_legacy_qr_payment_callback_is_still_honoured(): void
    {
        $this->withHeader('x-callback-token', self::TOKEN)
            ->postJson('/api/webhooks/xendit', [
                'event' => 'qr.payment',
                'data'  => [
                    'id'           => 'qrpy_legacy',
                    'qr_id'        => 'qr_legacy',
                    'reference_id' => $this->payment->reference_id,
                    'amount'       => 300000,
                    'status'       => 'SUCCEEDED',
                ],
            ])
            ->assertOk();

        $this->assertSame(PaymentStatus::Paid, $this->payment->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $this->order->fresh()->status);
        $this->assertSame(2, Ticket::where('order_id', $this->order->id)->count());
    }

    public function test_an_uninteresting_event_is_ignored(): void
    {
        $this->withHeader('x-callback-token', self::TOKEN)
            ->postJson('/api/webhooks/xendit', $this->payload(['status' => 'ACTIVE']))
            ->assertOk()
            ->assertJson(['status' => 'ignored']);

        $this->assertSame(PaymentStatus::Pending, $this->payment->fresh()->status);
    }
}
