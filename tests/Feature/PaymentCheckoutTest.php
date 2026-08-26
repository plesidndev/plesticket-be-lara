<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User  $buyer;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.xendit.secret_key', 'xnd_test_key');
        config()->set('services.xendit.callback_token', 'test-callback-token');

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

    private function fakeQrSuccess(): void
    {
        Http::fake([
            'api.xendit.co/v3/payment_requests' => Http::response([
                'payment_request_id' => 'pr_test_1',
                'reference_id'       => 'ORD202608260001-AB12CD',
                'currency'           => 'IDR',
                'amount'             => 300000,
                'status'             => 'REQUIRES_ACTION',
                'payment_method'     => [
                    'id'      => 'pm_test_1',
                    'type'    => 'QR_CODE',
                    'qr_code' => [
                        'channel_code'       => 'QRIS',
                        'channel_properties' => [
                            'qr_string'  => '00020101021226650013ID.CO.QRIS.WWW',
                            'expires_at' => now()->addMinutes(30)->toIso8601ZuluString(),
                        ],
                    ],
                ],
            ], 201),
        ]);
    }

    public function test_payment_methods_lists_only_enabled_methods_grouped_by_type(): void
    {
        $response = $this->getJson('/api/payment-methods')->assertOk();

        $response->assertJsonPath('data.0.type', 'qris');
        $response->assertJsonPath('data.0.methods.0.code', 'qris');
        $response->assertJsonCount(1, 'data');

        // The scaffolded BRI/Mandiri entries stay hidden while disabled.
        $this->assertStringNotContainsString('bri_va', $response->getContent());
    }

    public function test_it_creates_a_qris_charge_and_returns_the_qr_string(): void
    {
        $this->fakeQrSuccess();

        $response = $this->actingAs($this->buyer, 'api')
            ->postJson("/api/orders/{$this->order->order_number}/payments", [
                'method_code' => 'qris',
            ])
            ->assertCreated();

        $response->assertJsonPath('data.method_code', 'qris');
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.amount', 300000);
        $response->assertJsonPath('data.instruction.qr_string', '00020101021226650013ID.CO.QRIS.WWW');

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://api.xendit.co/v3/payment_requests'
                && $body['type'] === 'PAY'
                && $body['currency'] === 'IDR'
                && $body['payment_method']['type'] === 'QR_CODE'
                && $body['payment_method']['reusability'] === 'ONE_TIME_USE'
                && $body['payment_method']['qr_code']['channel_code'] === 'QRIS'
                // IDR has no minor unit — the amount must go out as an integer.
                && $body['amount'] === 300000
                && is_int($body['amount'])
                && str_starts_with($body['reference_id'], 'ORD202608260001-');
        });

        $this->assertDatabaseHas('payments', [
            'order_id'           => $this->order->id,
            'method_code'        => 'qris',
            'provider'           => 'xendit',
            'status'                    => 'pending',
            'provider_reference'        => 'pr_test_1',
            'provider_method_reference' => 'pm_test_1',
        ]);
    }

    public function test_order_checkout_can_create_the_order_and_payment_together(): void
    {
        $this->fakeQrSuccess();

        $response = $this->actingAs($this->buyer, 'api')
            ->postJson('/api/orders', [
                'event_id' => $this->order->event_id,
                'payment_method' => 'qris',
                'items' => [[
                    'ticket_type_id' => $this->order->items()->first()->ticket_type_id,
                    'quantity' => 1,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Order and payment created.')
            ->assertJsonPath('data.order.status', 'pending_payment')
            ->assertJsonPath('data.order.total_price', 150000)
            ->assertJsonPath('data.payment.method_code', 'qris')
            ->assertJsonPath('data.payment.status', 'pending')
            ->assertJsonPath(
                'data.payment.instruction.qr_string',
                '00020101021226650013ID.CO.QRIS.WWW',
            );

        $this->assertDatabaseHas('orders', [
            'order_number' => $response->json('data.order.order_number'),
            'buyer_id' => $this->buyer->id,
            'status' => 'pending_payment',
        ]);

        $this->assertDatabaseHas('payments', [
            'reference_id' => $response->json('data.payment.reference_id'),
            'method_code' => 'qris',
            'provider' => 'xendit',
            'status' => 'pending',
        ]);
    }

    public function test_order_creation_without_a_payment_method_remains_supported(): void
    {
        $this->actingAs($this->buyer, 'api')
            ->postJson('/api/orders', [
                'event_id' => $this->order->event_id,
                'items' => [[
                    'ticket_type_id' => $this->order->items()->first()->ticket_type_id,
                    'quantity' => 1,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Order created.')
            ->assertJsonPath('data.status', 'pending_payment');

        Http::assertNothingSent();
    }

    public function test_combined_checkout_keeps_the_order_available_when_the_gateway_fails(): void
    {
        Http::fake([
            'api.xendit.co/*' => Http::response([
                'error_code' => 'SERVER_ERROR',
                'message' => 'Xendit is temporarily unavailable.',
            ], 500),
        ]);

        $response = $this->actingAs($this->buyer, 'api')
            ->postJson('/api/orders', [
                'event_id' => $this->order->event_id,
                'payment_method' => 'qris',
                'items' => [[
                    'ticket_type_id' => $this->order->items()->first()->ticket_type_id,
                    'quantity' => 1,
                ]],
            ])
            ->assertStatus(502)
            ->assertJsonPath('message', 'Xendit is temporarily unavailable.')
            ->assertJsonPath('data.order.status', 'pending_payment');

        $orderNumber = $response->json('data.order.order_number');

        $this->assertNotEmpty($orderNumber);
        $this->assertStringContainsString(
            "/api/orders/{$orderNumber}/payments",
            $response->json('data.payment_retry_url'),
        );
        $this->assertDatabaseHas('orders', [
            'order_number' => $orderNumber,
            'status' => 'pending_payment',
        ]);
        $this->assertDatabaseHas('payments', [
            'order_id' => Order::where('order_number', $orderNumber)->value('id'),
            'status' => 'failed',
        ]);
    }

    public function test_requesting_a_payment_twice_reuses_the_live_charge(): void
    {
        $this->fakeQrSuccess();

        $first = $this->actingAs($this->buyer, 'api')
            ->postJson("/api/orders/{$this->order->order_number}/payments", ['method_code' => 'qris'])
            ->assertCreated();

        $second = $this->actingAs($this->buyer, 'api')
            ->postJson("/api/orders/{$this->order->order_number}/payments", ['method_code' => 'qris'])
            ->assertCreated();

        $this->assertSame(
            $first->json('data.reference_id'),
            $second->json('data.reference_id'),
            'A refreshed checkout must not mint a second QR code.',
        );

        Http::assertSentCount(1);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_a_disabled_method_is_rejected(): void
    {
        $this->actingAs($this->buyer, 'api')
            ->postJson("/api/orders/{$this->order->order_number}/payments", ['method_code' => 'bri_va'])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_an_unknown_method_is_a_404(): void
    {
        $this->actingAs($this->buyer, 'api')
            ->postJson("/api/orders/{$this->order->order_number}/payments", ['method_code' => 'gopay'])
            ->assertStatus(404);
    }

    public function test_a_gateway_failure_surfaces_as_502_and_leaves_no_usable_payment(): void
    {
        Http::fake([
            'api.xendit.co/*' => Http::response([
                'error_code' => 'INVALID_API_KEY',
                'message'    => 'The API key is invalid.',
            ], 401),
        ]);

        $this->actingAs($this->buyer, 'api')
            ->postJson("/api/orders/{$this->order->order_number}/payments", ['method_code' => 'qris'])
            ->assertStatus(502)
            ->assertJsonPath('message', 'The API key is invalid.');

        $this->assertSame(OrderStatus::PendingPayment, $this->order->fresh()->status);
    }

    public function test_another_buyer_cannot_pay_someone_elses_order(): void
    {
        $this->fakeQrSuccess();

        $intruder = User::create([
            'uid'      => 'USR0002',
            'name'     => 'Siti',
            'email'    => 'siti@example.com',
            'password' => 'secret',
            'role'     => 'REGISTERED_USER',
        ]);

        $this->actingAs($intruder, 'api')
            ->postJson("/api/orders/{$this->order->order_number}/payments", ['method_code' => 'qris'])
            ->assertStatus(404);

        Http::assertNothingSent();
    }

    public function test_an_expired_order_cannot_be_paid(): void
    {
        $this->fakeQrSuccess();
        $this->order->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($this->buyer, 'api')
            ->postJson("/api/orders/{$this->order->order_number}/payments", ['method_code' => 'qris'])
            ->assertStatus(422);

        $this->assertSame(OrderStatus::Expired, $this->order->fresh()->status);
        // Releasing the hold returns the seats.
        $this->assertSame(100, TicketType::first()->quota);
        Http::assertNothingSent();
    }

    public function test_creating_a_payment_requires_authentication(): void
    {
        $this->postJson("/api/orders/{$this->order->order_number}/payments", ['method_code' => 'qris'])
            ->assertStatus(401);
    }
}
