<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\WebhookDeliveryStatus;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebhookLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['uid' => 'SA0001', 'name' => 'Super Admin', 'email' => 'admin@example.com', 'password' => 'secret', 'role' => 'SUPER_ADMIN']);
    }

    private function delivery(WebhookDeliveryStatus $status, array $overrides = []): WebhookDelivery
    {
        return WebhookDelivery::create(array_merge([
            'id' => (string) Str::uuid(), 'provider' => PaymentProvider::Xendit, 'event_type' => 'payment.succeeded',
            'reference_id' => 'REF-001', 'status' => $status, 'payload' => ['ok' => true],
        ], $overrides));
    }

    public function test_the_log_shows_settled_deliveries_the_queue_hides(): void
    {
        $this->delivery(WebhookDeliveryStatus::Applied);
        $this->delivery(WebhookDeliveryStatus::Failed, ['error' => 'boom']);

        // The attention queue shows only what needs work.
        $this->actingAs($this->admin, 'api')->getJson('/api/admin/operations/webhooks')
            ->assertOk()->assertJsonCount(1, 'data');

        // The full log shows everything, payload included.
        $this->actingAs($this->admin, 'api')->getJson('/api/admin/operations/webhook-log')
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.payload.ok', true);
    }

    public function test_it_filters_the_log(): void
    {
        $this->delivery(WebhookDeliveryStatus::Applied);
        $this->delivery(WebhookDeliveryStatus::Failed, ['reference_id' => 'REF-002', 'error' => 'boom']);

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/operations/webhook-log?status=failed')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reference_id', 'REF-002');

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/operations/webhook-log?reference_id=REF-001')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'applied');
    }

    public function test_replaying_writes_a_new_delivery_and_leaves_the_original_alone(): void
    {
        $buyer = User::create(['uid' => 'U000001', 'name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER']);
        $event = Event::create([
            'event_id' => 'EVT0001', 'user_id' => $buyer->id, 'title' => 'Konser', 'slug' => 'konser',
            'pic_name' => 'P', 'pic_identity_type' => 'ktp', 'pic_identity_number' => '3200000000000001',
            'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->toDateString(),
            'verification_status' => 'verified',
        ]);
        $order = Order::create(['order_number' => 'ORD0001', 'buyer_id' => $buyer->id, 'buyer_name' => 'Budi',
            'event_id' => $event->id, 'status' => OrderStatus::PendingPayment, 'total_price' => 150000]);
        Payment::create(['order_id' => $order->id, 'reference_id' => 'REF-XYZ', 'provider' => PaymentProvider::Xendit,
            'method_code' => 'qris', 'type' => PaymentType::Qris, 'status' => PaymentStatus::Pending, 'amount' => 150000]);

        $original = $this->delivery(WebhookDeliveryStatus::Failed, [
            'reference_id' => 'REF-XYZ', 'error' => 'transient blip',
            'payload' => ['event' => 'payment.succeeded', 'data' => ['reference_id' => 'REF-XYZ', 'status' => 'PAID', 'amount' => 150000]],
        ]);

        $this->actingAs($this->admin, 'api')->postJson("/api/admin/operations/webhook-log/{$original->id}/replay")->assertOk();

        // The original still reads as failed — history is not rewritten.
        $this->assertSame(WebhookDeliveryStatus::Failed, $original->fresh()->status);
        $this->assertSame('transient blip', $original->fresh()->error);
        $this->assertSame(2, WebhookDelivery::count());

        // And the replay is attributed.
        $this->assertSame(1, AuditLog::where('action', 'webhook.replayed')->count());
    }

    public function test_it_refuses_to_replay_a_payload_carrying_no_event(): void
    {
        $original = $this->delivery(WebhookDeliveryStatus::Ignored, ['payload' => ['event' => 'something.irrelevant']]);

        $this->actingAs($this->admin, 'api')->postJson("/api/admin/operations/webhook-log/{$original->id}/replay")
            ->assertStatus(422);

        // Nothing was recorded for an attempt that could never have applied.
        $this->assertSame(1, WebhookDelivery::count());
    }

    public function test_it_404s_replaying_an_unknown_delivery(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/admin/operations/webhook-log/'.Str::uuid().'/replay')->assertNotFound();
    }

    public function test_reading_needs_operations_view_and_replaying_needs_operations_manage(): void
    {
        $original = $this->delivery(WebhookDeliveryStatus::Failed);

        $limited = User::create(['uid' => 'AD0002', 'name' => 'Limited', 'email' => 'limited@example.com', 'password' => 'secret', 'role' => 'ADMIN']);
        $limited->permissions()->create(['permission' => 'console.access']);

        $this->actingAs($limited, 'api')->getJson('/api/admin/operations/webhook-log')->assertForbidden();

        $limited->permissions()->create(['permission' => 'operations.view']);
        $this->actingAs($limited->fresh(), 'api')->getJson('/api/admin/operations/webhook-log')->assertOk();

        // Reading the log must not be enough to re-apply payment state.
        $this->actingAs($limited->fresh(), 'api')->postJson("/api/admin/operations/webhook-log/{$original->id}/replay")->assertForbidden();

        $limited->permissions()->create(['permission' => 'operations.manage']);
        $this->actingAs($limited->fresh(), 'api')->postJson("/api/admin/operations/webhook-log/{$original->id}/replay")->assertStatus(422);
    }
}
