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
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WebhookDeliveryAuditTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-callback-token';

    private Payment $payment;
    private Order   $order;

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

    private function send(array $data, ?string $token = self::TOKEN, string $event = 'payment.succeeded')
    {
        $request = $token === null ? $this : $this->withHeader('x-callback-token', $token);

        return $request->postJson('/api/webhooks/xendit', [
            'event' => $event,
            'data'  => $data,
        ]);
    }

    private function succeeded(array $overrides = []): array
    {
        return array_merge([
            'id'                 => 'ps_1',
            'payment_request_id' => 'pr_1',
            'reference_id'       => $this->payment->reference_id,
            'amount'             => 300000,
            'status'             => 'SUCCEEDED',
        ], $overrides);
    }

    public function test_a_handled_callback_is_recorded_with_its_payload(): void
    {
        $this->send($this->succeeded())->assertOk();

        $delivery = WebhookDelivery::sole();

        $this->assertSame(PaymentProvider::Xendit, $delivery->provider);
        $this->assertSame('payment.succeeded', $delivery->event_type);
        $this->assertSame($this->payment->reference_id, $delivery->reference_id);
        $this->assertSame(WebhookDeliveryStatus::Applied, $delivery->status);
        $this->assertNotNull($delivery->processed_at);
        $this->assertNull($delivery->error);

        // The raw payload is kept verbatim, so a delivery can be replayed.
        $this->assertSame('SUCCEEDED', $delivery->payload['data']['status']);
        $this->assertSame(300000, $delivery->payload['data']['amount']);
    }

    /**
     * The case this table exists for: money moved, and we have nothing to
     * attach it to.
     */
    public function test_an_unmatched_callback_is_kept_for_investigation(): void
    {
        $this->send($this->succeeded(['reference_id' => 'ORD-NEVER-SEEN']))->assertOk();

        $delivery = WebhookDelivery::sole();

        $this->assertSame(WebhookDeliveryStatus::Unmatched, $delivery->status);
        $this->assertTrue($delivery->needsAttention());
        $this->assertSame('ORD-NEVER-SEEN', $delivery->reference_id);
        $this->assertSame('ORD-NEVER-SEEN', $delivery->payload['data']['reference_id']);
    }

    public function test_a_rejected_callback_is_not_stored_at_all(): void
    {
        $this->send($this->succeeded(), token: 'wrong-token')->assertStatus(401);

        // The endpoint is public — an invalid token must not let anyone write.
        $this->assertDatabaseCount('webhook_deliveries', 0);
    }

    public function test_an_ignored_event_is_recorded_as_ignored(): void
    {
        $this->send($this->succeeded(['status' => 'PENDING']))->assertOk();

        $delivery = WebhookDelivery::sole();

        $this->assertSame(WebhookDeliveryStatus::Ignored, $delivery->status);
        $this->assertFalse($delivery->needsAttention());
        // Unparseable events still keep their payload.
        $this->assertSame('PENDING', $delivery->payload['data']['status']);
    }

    public function test_a_redelivery_is_recorded_separately_and_marked_skipped(): void
    {
        $this->send($this->succeeded())->assertOk();
        $this->send($this->succeeded())->assertOk();

        $this->assertDatabaseCount('webhook_deliveries', 2);

        $statuses = WebhookDelivery::orderBy('created_at')->pluck('status')->all();

        $this->assertSame(WebhookDeliveryStatus::Applied, $statuses[0]);
        $this->assertSame(WebhookDeliveryStatus::Skipped, $statuses[1]);
    }

    public function test_a_failure_is_recorded_with_its_error_and_asks_for_a_retry(): void
    {
        $this->mock(PaymentRepositoryInterface::class, function ($mock) {
            $mock->shouldReceive('findByReferenceId')
                ->andThrow(new RuntimeException('database is on fire'));
        });

        $this->send($this->succeeded())->assertStatus(500);

        $delivery = WebhookDelivery::sole();

        $this->assertSame(WebhookDeliveryStatus::Failed, $delivery->status);
        $this->assertTrue($delivery->needsAttention());
        $this->assertSame('database is on fire', $delivery->error);

        // The payload survived the failure, so the callback can be replayed.
        $this->assertSame($this->payment->reference_id, $delivery->payload['data']['reference_id']);
    }
}
