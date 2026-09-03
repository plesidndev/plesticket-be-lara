<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\WebhookDeliveryStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Talent;
use App\Models\User;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminConsoleApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $user;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['uid' => 'SA0001', 'name' => 'Super Admin', 'email' => 'admin@example.com', 'password' => 'secret', 'role' => 'SUPER_ADMIN']);
        $this->user = User::create(['uid' => 'U000001', 'name' => 'Budi Santoso', 'email' => 'budi@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER']);
        Category::create(['name' => 'Music', 'is_active' => true]);
        $this->event = Event::create([
            'event_id' => 'EVT0001', 'user_id' => $this->user->id, 'title' => 'Jakarta Music Fest', 'slug' => 'jakarta-music-fest',
            'category' => 'Music', 'pic_name' => 'Budi', 'pic_identity_type' => 'ktp', 'pic_identity_number' => '3173000000000001',
            'start_date' => '2026-09-10', 'end_date' => '2026-09-10', 'verification_status' => 'pending',
        ]);
        Talent::create(['name' => 'Sunset Band', 'slug' => 'sunset-band', 'is_verified' => false, 'submitted_by' => $this->user->id]);
    }

    public function test_admin_searches_users_and_events_on_the_deployed_database_operator(): void
    {
        $this->actingAs($this->admin, 'api')->getJson('/api/users?search=budi')
            ->assertOk()->assertJsonPath('data.0.uid', 'U000001');

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/events?search=jakarta')
            ->assertOk()->assertJsonPath('data.0.event_id', 'EVT0001');
    }

    public function test_unknown_users_return_404_for_read_and_write_requests(): void
    {
        $this->actingAs($this->admin, 'api')->getJson('/api/users/U-NOPE')->assertNotFound();
        $this->actingAs($this->admin, 'api')->putJson('/api/users/U-NOPE', ['name' => 'Missing'])->assertNotFound();
    }

    public function test_talents_use_the_plural_table_and_are_available_to_admins(): void
    {
        $this->assertSame('talents', (new Talent)->getTable());
        $this->actingAs($this->admin, 'api')->getJson('/api/admin/talents?is_verified=false')
            ->assertOk()->assertJsonPath('data.0.name', 'Sunset Band');
    }

    public function test_admin_summary_returns_real_metrics(): void
    {
        $this->actingAs($this->admin, 'api')->getJson('/api/admin/summary')->assertOk()->assertJsonPath('data', [
            'pending_events' => 1, 'verified_events' => 0, 'active_users' => 2, 'active_categories' => 1,
            'pending_talents' => 1, 'refunds_requiring_attention' => 0, 'webhooks_requiring_attention' => 0,
        ]);
    }

    public function test_admin_operations_endpoints_return_only_items_requiring_attention(): void
    {
        $order = Order::create(['order_number' => 'ORD0001', 'buyer_id' => $this->user->id, 'event_id' => $this->event->id, 'status' => OrderStatus::Paid, 'total_price' => 150000]);
        Payment::create(['order_id' => $order->id, 'reference_id' => 'REF-001', 'provider' => PaymentProvider::Xendit, 'method_code' => 'qris', 'type' => PaymentType::Qris, 'status' => PaymentStatus::Paid, 'amount' => 150000, 'requires_refund' => true, 'paid_at' => now()]);
        WebhookDelivery::create(['provider' => PaymentProvider::Xendit, 'event_type' => 'payment.succeeded', 'reference_id' => 'REF-MISSING', 'status' => WebhookDeliveryStatus::Unmatched, 'payload' => []]);

        $this->actingAs($this->admin, 'api')->getJson('/api/admin/operations/refunds')->assertOk()->assertJsonPath('data.0.reference_id', 'REF-001');
        $this->actingAs($this->admin, 'api')->getJson('/api/admin/operations/webhooks')->assertOk()->assertJsonPath('data.0.reference_id', 'REF-MISSING');
    }

    public function test_non_admins_cannot_access_admin_operational_data(): void
    {
        $this->actingAs($this->user, 'api')->getJson('/api/admin/summary')->assertForbidden();
        $this->actingAs($this->user, 'api')->getJson('/api/admin/operations/refunds')->assertForbidden();
    }
}
