<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentMethodSwitchTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-callback-token';

    private User  $buyer;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.xendit.secret_key', 'xnd_test_key');
        config()->set('services.xendit.callback_token', self::TOKEN);

        // Turn on BRI VA so a switch has somewhere to go.
        $methods = collect(config('payments.methods'))
            ->map(fn(array $m) => $m['code'] === 'bri_va' ? array_merge($m, ['enabled' => true]) : $m)
            ->all();
        config()->set('payments.methods', $methods);

        $this->buyer = User::create([
            'uid'      => 'USR0001',
            'name'     => 'Budi Santoso',
            'email'    => 'budi@example.com',
            'password' => 'secret',
            'role'     => 'REGISTERED_USER',
        ]);

        $event = Event::create([
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

        $ticketType = TicketType::create([
            'event_id' => $event->id,
            'name'     => 'Presale',
            'price'    => 150000,
            'quota'    => 98,
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD202608260001',
            'buyer_id'     => $this->buyer->id,
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
    }

    /**
     * Both instruments now come from the same endpoint, so the fake keys off
     * the requested payment method type.
     */
    private function fakeXendit(): void
    {
        $sequence = 0;

        Http::fake([
            'api.xendit.co/v3/payment_methods/*/expire' => Http::response([
                'id'     => 'pm_expired',
                'status' => 'EXPIRED',
            ], 200),

            'api.xendit.co/v3/payment_requests' => function ($request) use (&$sequence) {
                $sequence++;
                $type = $request->data()['payment_method']['type'];

                if ($type === 'QR_CODE') {
                    return Http::response([
                        'payment_request_id' => "pr_qris_{$sequence}",
                        'status'             => 'REQUIRES_ACTION',
                        'payment_method'     => [
                            'id'      => "pm_qris_{$sequence}",
                            'type'    => 'QR_CODE',
                            'qr_code' => [
                                'channel_properties' => [
                                    'qr_string' => '00020101021226650013ID.CO.QRIS.WWW',
                                ],
                            ],
                        ],
                    ], 201);
                }

                return Http::response([
                    'payment_request_id' => "pr_va_{$sequence}",
                    'status'             => 'REQUIRES_ACTION',
                    'payment_method'     => [
                        'id'              => "pm_va_{$sequence}",
                        'type'            => 'VIRTUAL_ACCOUNT',
                        'virtual_account' => [
                            'channel_code'       => 'BRI',
                            'channel_properties' => [
                                'virtual_account_number' => '8808123456789',
                            ],
                        ],
                    ],
                ], 201);
            },
        ]);
    }

    private function pay(string $methodCode)
    {
        return $this->actingAs($this->buyer, 'api')
            ->postJson("/api/orders/{$this->order->order_number}/payments", [
                'method_code' => $methodCode,
            ]);
    }

    private function webhook(string $referenceId, array $overrides = [])
    {
        return $this->withHeader('x-callback-token', self::TOKEN)
            ->postJson('/api/webhooks/xendit', [
                'event' => 'payment.succeeded',
                'data'  => array_merge([
                    'id'                 => 'ps_1',
                    'payment_request_id' => 'pr_qris_1',
                    'reference_id'       => $referenceId,
                    'amount'             => 300000,
                    'status'             => 'SUCCEEDED',
                ], $overrides),
            ]);
    }

    public function test_switching_from_qris_to_bri_cancels_the_qris_charge(): void
    {
        $this->fakeXendit();

        $qris = $this->pay('qris')->assertCreated();
        $bri  = $this->pay('bri_va')->assertCreated();

        $qrisPayment = Payment::where('reference_id', $qris->json('data.reference_id'))->first();
        $briPayment  = Payment::where('reference_id', $bri->json('data.reference_id'))->first();

        $this->assertSame(PaymentStatus::Cancelled, $qrisPayment->status);
        $this->assertSame(PaymentStatus::Pending, $briPayment->status);

        // The new instruction is a VA number, not a QR string.
        $bri->assertJsonPath('data.method_code', 'bri_va');
        $bri->assertJsonPath('data.instruction.account_number', '8808123456789');
        $this->assertNull($bri->json('data.instruction.qr_string'));

        // Exactly one payable charge remains on the order.
        $this->assertSame(1, Payment::where('order_id', $this->order->id)
            ->where('status', PaymentStatus::Pending)->count());
    }

    public function test_switching_back_and_forth_leaves_only_the_latest_charge_live(): void
    {
        $this->fakeXendit();

        $this->pay('qris')->assertCreated();
        $this->pay('bri_va')->assertCreated();
        $latest = $this->pay('qris')->assertCreated();

        $this->assertSame(3, Payment::where('order_id', $this->order->id)->count());

        $live = Payment::where('order_id', $this->order->id)
            ->where('status', PaymentStatus::Pending)->get();

        $this->assertCount(1, $live);
        $this->assertSame($latest->json('data.reference_id'), $live->first()->reference_id);
        $this->assertSame('qris', $live->first()->method_code);
    }

    public function test_switching_expires_the_abandoned_virtual_account_at_xendit(): void
    {
        $this->fakeXendit();

        $bri = $this->pay('bri_va')->assertCreated();
        $this->pay('qris')->assertCreated();

        Http::assertSent(fn($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/v3/payment_methods/pm_va_1/expire'));

        $this->assertSame(
            PaymentStatus::Cancelled,
            Payment::where('reference_id', $bri->json('data.reference_id'))->first()->status,
        );
    }

    /**
     * The reason for moving to the Payment Requests API: a QR can now actually
     * be retracted, rather than merely abandoned until it lapses.
     */
    public function test_switching_away_from_qris_expires_the_qr_at_xendit(): void
    {
        $this->fakeXendit();

        $qris = $this->pay('qris')->assertCreated();
        $this->pay('bri_va')->assertCreated();

        Http::assertSent(fn($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/v3/payment_methods/pm_qris_1/expire'));

        $this->assertSame(
            PaymentStatus::Cancelled,
            Payment::where('reference_id', $qris->json('data.reference_id'))->first()->status,
        );
    }

    public function test_a_provider_that_cannot_retract_still_lets_the_switch_through(): void
    {
        Http::fake([
            'api.xendit.co/v3/payment_methods/*/expire' => Http::response(['message' => 'nope'], 500),
            'api.xendit.co/v3/payment_requests' => Http::response([
                'payment_request_id' => 'pr_1',
                'payment_method'     => [
                    'id'              => 'pm_1',
                    'qr_code'         => ['channel_properties' => ['qr_string' => 'QR']],
                    'virtual_account' => ['channel_properties' => ['virtual_account_number' => '8808123456789']],
                ],
            ], 201),
        ]);

        $bri = $this->pay('bri_va')->assertCreated();

        // Xendit refuses the retraction, but the buyer still gets their QR.
        $this->pay('qris')->assertCreated();

        $this->assertSame(
            PaymentStatus::Cancelled,
            Payment::where('reference_id', $bri->json('data.reference_id'))->first()->status,
        );
    }

    public function test_paying_an_abandoned_qris_still_settles_the_order(): void
    {
        $this->fakeXendit();

        $qris = $this->pay('qris')->assertCreated();
        $this->pay('bri_va')->assertCreated();

        // Belt and braces: even with the QR expired at Xendit, a confirmation
        // that still arrives for it must be honoured rather than dropped.
        $this->webhook($qris->json('data.reference_id'))->assertOk();

        $order = $this->order->fresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('qris', $order->payment_method);
        $this->assertSame(2, Ticket::where('order_id', $order->id)->count());

        $qrisPayment = Payment::where('reference_id', $qris->json('data.reference_id'))->first();
        $this->assertSame(PaymentStatus::Paid, $qrisPayment->status);
        $this->assertFalse($qrisPayment->requires_refund);
    }

    public function test_paying_both_methods_flags_the_second_for_refund_without_double_tickets(): void
    {
        $this->fakeXendit();

        $qris = $this->pay('qris')->assertCreated();
        $bri  = $this->pay('bri_va')->assertCreated();

        // The buyer transfers to the VA...
        $this->webhook($bri->json('data.reference_id'))->assertOk();
        // ...and then also scans the abandoned QR.
        $this->webhook($qris->json('data.reference_id'))->assertOk();

        $this->assertSame(OrderStatus::Paid, $this->order->fresh()->status);

        // Still one set of tickets.
        $this->assertSame(2, Ticket::where('order_id', $this->order->id)->count());

        $briPayment  = Payment::where('reference_id', $bri->json('data.reference_id'))->first();
        $qrisPayment = Payment::where('reference_id', $qris->json('data.reference_id'))->first();

        $this->assertSame(PaymentStatus::Paid, $briPayment->status);
        $this->assertFalse($briPayment->requires_refund);

        // The duplicate is recorded as real money owed back.
        $this->assertSame(PaymentStatus::Paid, $qrisPayment->status);
        $this->assertTrue($qrisPayment->requires_refund);

        $this->assertSame(1, Payment::where('order_id', $this->order->id)
            ->where('requires_refund', true)->count());
    }

    public function test_switching_is_refused_once_the_order_is_paid(): void
    {
        $this->fakeXendit();

        $qris = $this->pay('qris')->assertCreated();
        $this->webhook($qris->json('data.reference_id'))->assertOk();

        $this->pay('bri_va')->assertStatus(422);

        $this->assertSame(1, Payment::where('order_id', $this->order->id)->count());
    }
}
