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

class ExpireAbandonedOrdersTest extends TestCase
{
    use RefreshDatabase;

    private User       $buyer;
    private Event      $event;
    private TicketType $ticketType;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.xendit.callback_token', 'test-callback-token');

        $this->buyer = User::create([
            'uid'      => 'USR0001',
            'name'     => 'Budi Santoso',
            'email'    => 'budi@example.com',
            'password' => 'secret',
            'role'     => 'REGISTERED_USER',
        ]);

        $this->event = Event::create([
            'event_id'            => 'EVT0001',
            'user_id'             => $this->buyer->id,
            'title'               => 'Konser Senja',
            'slug'                => 'konser-senja',
            'pic_name'            => 'Panitia',
            'pic_identity_type'   => 'ktp',
            'pic_identity_number' => '3200000000000001',
            'start_date'          => now()->addWeek()->toDateString(),
            'end_date'            => now()->addWeek()->toDateString(),
            'verification_status' => 'verified',
        ]);

        // 100 seats, less the 2 the order below holds.
        $this->ticketType = TicketType::create([
            'event_id' => $this->event->id,
            'name'     => 'Presale',
            'price'    => 150000,
            'quota'    => 98,
        ]);
    }

    private function order(array $attrs = []): Order
    {
        static $n = 0;
        $n++;

        $order = Order::create(array_merge([
            'order_number' => sprintf('ORD2026082600%02d', $n),
            'buyer_id'     => $this->buyer->id,
            'event_id'     => $this->event->id,
            'status'       => OrderStatus::PendingPayment,
            'total_price'  => 300000,
            'expires_at'   => now()->subMinute(),
        ], $attrs));

        $order->items()->create([
            'ticket_type_id'   => $this->ticketType->id,
            'ticket_type_name' => 'Presale',
            'unit_price'       => 150000,
            'quantity'         => 2,
            'subtotal'         => 300000,
        ]);

        return $order->fresh();
    }

    public function test_it_releases_quota_from_a_lapsed_order(): void
    {
        $order = $this->order();

        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
        $this->assertSame(100, $this->ticketType->fresh()->quota);
    }

    public function test_it_leaves_an_order_that_still_has_time(): void
    {
        $order = $this->order(['expires_at' => now()->addMinutes(10)]);

        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertSame(98, $this->ticketType->fresh()->quota);
    }

    public function test_it_ignores_paid_and_cancelled_orders(): void
    {
        $paid = $this->order(['status' => OrderStatus::Paid]);
        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame(OrderStatus::Paid, $paid->fresh()->status);
        // A paid order's seats stay sold.
        $this->assertSame(98, $this->ticketType->fresh()->quota);
    }

    public function test_running_it_twice_does_not_restore_quota_twice(): void
    {
        $this->order();

        $this->artisan('orders:expire')->assertSuccessful();
        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame(100, $this->ticketType->fresh()->quota);
    }

    public function test_it_expires_the_pending_charge_alongside_the_order(): void
    {
        $order = $this->order();

        $payment = Payment::create([
            'order_id'     => $order->id,
            'reference_id' => 'REF-ABANDONED',
            'provider'     => PaymentProvider::Xendit,
            'method_code'  => 'qris',
            'type'         => PaymentType::Qris,
            'status'       => PaymentStatus::Pending,
            'amount'       => 300000,
            'expires_at'   => now()->subMinute(),
        ]);

        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame(PaymentStatus::Expired, $payment->fresh()->status);
    }

    public function test_the_limit_option_caps_a_run(): void
    {
        $this->ticketType->update(['quota' => 94]);
        $this->order();
        $this->order();
        $this->order();

        $this->artisan('orders:expire', ['--limit' => 2])->assertSuccessful();

        $this->assertSame(2, Order::where('status', OrderStatus::Expired)->count());
        $this->assertSame(1, Order::where('status', OrderStatus::PendingPayment)->count());
    }

    /**
     * The race the sweeper creates: money arrives for an order whose seats we
     * already put back. Issuing tickets would oversell, so the payment is taken
     * into the refund queue instead.
     */
    public function test_a_payment_landing_after_the_sweep_is_flagged_for_refund(): void
    {
        $order = $this->order();

        $payment = Payment::create([
            'order_id'     => $order->id,
            'reference_id' => 'REF-LATE',
            'provider'     => PaymentProvider::Xendit,
            'method_code'  => 'qris',
            'type'         => PaymentType::Qris,
            'status'       => PaymentStatus::Pending,
            'amount'       => 300000,
            'expires_at'   => now()->subMinute(),
        ]);

        $this->artisan('orders:expire')->assertSuccessful();
        $payment->update(['status' => PaymentStatus::Pending]);

        $this->withHeader('x-callback-token', 'test-callback-token')
            ->postJson('/api/webhooks/xendit', [
                'event' => 'payment.succeeded',
                'data'  => [
                    'reference_id' => 'REF-LATE',
                    'amount'       => 300000,
                    'status'       => 'SUCCEEDED',
                ],
            ])->assertOk();

        $payment->refresh();

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertTrue($payment->requires_refund);

        // No tickets, and the released seats stay released.
        $this->assertSame(0, Ticket::where('order_id', $order->id)->count());
        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
        $this->assertSame(100, $this->ticketType->fresh()->quota);
    }
}
