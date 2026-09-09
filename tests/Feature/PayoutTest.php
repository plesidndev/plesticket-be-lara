<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PayoutStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrganizerMember;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $organizer;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('platform.fee_percent', 5);

        $this->admin = User::create(['uid' => 'SA0001', 'name' => 'Super Admin', 'email' => 'admin@example.com', 'password' => 'secret', 'role' => 'SUPER_ADMIN']);
        $this->organizer = User::create(['uid' => 'U000001', 'name' => 'Andi', 'email' => 'andi@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER', 'is_organizer' => true]);
        $this->event = Event::create([
            'event_id' => 'EVT0001', 'user_id' => $this->organizer->id, 'title' => 'Konser', 'slug' => 'konser',
            'pic_name' => 'P', 'pic_identity_type' => 'ktp', 'pic_identity_number' => '3200000000000001',
            'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->toDateString(),
            'verification_status' => 'verified',
        ]);
    }

    private function paidOrder(string $number, float $total, ?OrganizerMember $agent = null, ?string $paidAt = null): Order
    {
        return Order::create([
            'order_number' => $number, 'buyer_name' => 'Budi', 'event_id' => $this->event->id,
            'status' => OrderStatus::Paid, 'total_price' => $total,
            'paid_at' => $paidAt ?? now()->toDateTimeString(),
            'agent_id' => $agent?->id, 'is_agent_sale' => $agent !== null,
        ]);
    }

    private function agent(float $rate): OrganizerMember
    {
        return OrganizerMember::create([
            'event_id' => $this->event->id, 'owner_id' => $this->organizer->id,
            'uid' => 'OM0001', 'name' => 'Box', 'password' => 'secret',
            'role' => 'MITRA_TICKET_BOX', 'commission_rate' => $rate, 'is_active' => true,
        ]);
    }

    private function preview(): array
    {
        return $this->actingAs($this->admin, 'api')->postJson('/api/admin/payouts/preview', [
            'organizer_id' => $this->organizer->id, 'from' => now()->subMonth()->toDateString(), 'to' => now()->addDay()->toDateString(),
        ])->assertOk()->json('data');
    }

    public function test_it_deducts_the_platform_fee_from_gross(): void
    {
        $this->paidOrder('ORD0001', 1_000_000);

        $preview = $this->preview();

        $this->assertEquals(1_000_000.0, $preview['totals']['gross_amount']);
        $this->assertEquals(50_000.0, $preview['totals']['platform_fee_amount']);
        $this->assertEquals(950_000.0, $preview['totals']['net_amount']);
    }

    public function test_an_event_rate_overrides_the_platform_default(): void
    {
        $this->event->update(['platform_fee_percent' => 10]);
        $this->paidOrder('ORD0001', 1_000_000);

        $preview = $this->preview();

        $this->assertEquals(100_000.0, $preview['totals']['platform_fee_amount']);
        $this->assertEquals(900_000.0, $preview['totals']['net_amount']);
    }

    public function test_it_deducts_agent_commission_on_an_agent_sale(): void
    {
        $this->paidOrder('ORD0001', 1_000_000, $this->agent(12.5));

        $preview = $this->preview();

        // 5% platform + 12.5% agent, both on gross.
        $this->assertEquals(50_000.0, $preview['totals']['platform_fee_amount']);
        $this->assertEquals(125_000.0, $preview['totals']['agent_commission_amount']);
        $this->assertEquals(825_000.0, $preview['totals']['net_amount']);
    }

    public function test_it_does_not_deduct_the_payment_provider_fee(): void
    {
        $order = $this->paidOrder('ORD0001', 1_000_000);
        Payment::create(['order_id' => $order->id, 'reference_id' => 'REF-1', 'provider' => PaymentProvider::Xendit,
            'method_code' => 'qris', 'type' => PaymentType::Qris, 'status' => PaymentStatus::Paid,
            'amount' => 1_000_000, 'paid_at' => now()]);

        // The platform absorbs the provider fee out of its own share, so the organizer sees gross less 5%.
        $this->assertEquals(950_000.0, $this->preview()['totals']['net_amount']);
    }

    public function test_it_withholds_an_order_flagged_for_refund(): void
    {
        $this->paidOrder('ORD0001', 1_000_000);
        $flagged = $this->paidOrder('ORD0002', 500_000);
        Payment::create(['order_id' => $flagged->id, 'reference_id' => 'REF-2', 'provider' => PaymentProvider::Xendit,
            'method_code' => 'qris', 'type' => PaymentType::Qris, 'status' => PaymentStatus::Paid,
            'amount' => 500_000, 'requires_refund' => true, 'paid_at' => now()]);

        $preview = $this->preview();

        $this->assertEquals(1, $preview['totals']['orders']);
        $this->assertEquals(1_000_000.0, $preview['totals']['gross_amount']);
    }

    public function test_it_ignores_unpaid_orders_and_other_organizers(): void
    {
        $this->paidOrder('ORD0001', 1_000_000);
        Order::create(['order_number' => 'ORD0002', 'buyer_name' => 'B', 'event_id' => $this->event->id,
            'status' => OrderStatus::PendingPayment, 'total_price' => 999_999]);

        $rival = User::create(['uid' => 'U000009', 'name' => 'Rival', 'email' => 'rival@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER', 'is_organizer' => true]);
        $rivalEvent = Event::create(['event_id' => 'EVT0002', 'user_id' => $rival->id, 'title' => 'Other', 'slug' => 'other',
            'pic_name' => 'P', 'pic_identity_type' => 'ktp', 'pic_identity_number' => '3200000000000002',
            'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->toDateString(), 'verification_status' => 'verified']);
        Order::create(['order_number' => 'ORD0003', 'buyer_name' => 'C', 'event_id' => $rivalEvent->id,
            'status' => OrderStatus::Paid, 'total_price' => 777_777, 'paid_at' => now()]);

        $this->assertEquals(1_000_000.0, $this->preview()['totals']['gross_amount']);
    }

    public function test_an_order_is_never_paid_out_twice(): void
    {
        $this->paidOrder('ORD0001', 1_000_000);

        $body = ['organizer_id' => $this->organizer->id, 'from' => now()->subMonth()->toDateString(), 'to' => now()->addDay()->toDateString()];

        $this->actingAs($this->admin, 'api')->postJson('/api/admin/payouts', $body)->assertCreated();

        // The same window a second time has nothing left to pay.
        $this->actingAs($this->admin, 'api')->postJson('/api/admin/payouts', $body)->assertStatus(422);
        $this->assertEquals(0, $this->preview()['totals']['orders']);
    }

    public function test_cancelling_releases_the_orders_for_a_corrected_run(): void
    {
        $this->paidOrder('ORD0001', 1_000_000);
        $body = ['organizer_id' => $this->organizer->id, 'from' => now()->subMonth()->toDateString(), 'to' => now()->addDay()->toDateString()];

        $id = $this->actingAs($this->admin, 'api')->postJson('/api/admin/payouts', $body)->assertCreated()->json('data.id');

        $this->actingAs($this->admin, 'api')->postJson('/api/admin/payouts/cancel', ['id' => $id, 'note' => 'wrong window'])->assertOk();

        $this->assertEquals(1, $this->preview()['totals']['orders']);
    }

    public function test_the_lifecycle_runs_pending_to_approved_to_paid(): void
    {
        $this->paidOrder('ORD0001', 1_000_000);
        $id = $this->actingAs($this->admin, 'api')->postJson('/api/admin/payouts', [
            'organizer_id' => $this->organizer->id, 'from' => now()->subMonth()->toDateString(), 'to' => now()->addDay()->toDateString(),
        ])->assertCreated()->json('data.id');

        // Paying before approval is refused.
        $this->actingAs($this->admin, 'api')->postJson("/api/admin/payouts/{$id}/pay", ['bank_reference' => 'TRX-1'])->assertStatus(422);

        $this->actingAs($this->admin, 'api')->postJson("/api/admin/payouts/{$id}/approve")->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->actingAs($this->admin, 'api')->postJson("/api/admin/payouts/{$id}/pay", ['bank_reference' => 'TRX-1'])->assertOk()
            ->assertJsonPath('data.status', 'paid')->assertJsonPath('data.bank_reference', 'TRX-1');

        // A settled payout is immutable.
        $this->actingAs($this->admin, 'api')->postJson('/api/admin/payouts/cancel', ['id' => $id])->assertStatus(422);
        $this->assertSame(PayoutStatus::Paid, Payout::find($id)->status);
    }

    public function test_amounts_are_frozen_when_the_payout_is_created(): void
    {
        $this->paidOrder('ORD0001', 1_000_000);
        $id = $this->actingAs($this->admin, 'api')->postJson('/api/admin/payouts', [
            'organizer_id' => $this->organizer->id, 'from' => now()->subMonth()->toDateString(), 'to' => now()->addDay()->toDateString(),
        ])->assertCreated()->json('data.id');

        // Changing the rate afterwards must not restate what the organizer was already promised.
        config()->set('platform.fee_percent', 50);

        $this->actingAs($this->admin, 'api')->getJson("/api/admin/payouts/{$id}")->assertOk()
            ->assertJsonPath('data.net_amount', 950_000);
    }

    public function test_reading_needs_payouts_view_and_acting_needs_payouts_manage(): void
    {
        $limited = User::create(['uid' => 'AD0002', 'name' => 'Limited', 'email' => 'limited@example.com', 'password' => 'secret', 'role' => 'ADMIN']);
        $limited->permissions()->create(['permission' => 'console.access']);

        $this->actingAs($limited, 'api')->getJson('/api/admin/payouts')->assertForbidden();

        $limited->permissions()->create(['permission' => 'payouts.view']);
        $this->actingAs($limited->fresh(), 'api')->getJson('/api/admin/payouts')->assertOk();

        // Reading what is owed must not be enough to create or settle a payment.
        $this->actingAs($limited->fresh(), 'api')->postJson('/api/admin/payouts', [
            'organizer_id' => $this->organizer->id, 'from' => now()->subMonth()->toDateString(), 'to' => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_an_organizer_sees_only_their_own_payouts(): void
    {
        $this->paidOrder('ORD0001', 1_000_000);
        $this->actingAs($this->admin, 'api')->postJson('/api/admin/payouts', [
            'organizer_id' => $this->organizer->id, 'from' => now()->subMonth()->toDateString(), 'to' => now()->addDay()->toDateString(),
        ])->assertCreated();

        $rival = User::create(['uid' => 'U000009', 'name' => 'Rival', 'email' => 'rival@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER', 'is_organizer' => true]);

        $this->actingAs($this->organizer, 'api')->getJson('/api/payouts/mine')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.net_amount', 950000);

        // Another organizer's payout must not leak into their list.
        $this->actingAs($rival, 'api')->getJson('/api/payouts/mine')->assertOk()->assertJsonCount(0, 'data');
    }
}
