<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Event $event;

    private TicketType $presale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create(['uid' => 'U000001', 'name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER', 'is_organizer' => true]);
        $this->event = Event::create([
            'event_id' => 'EVT0001', 'user_id' => $this->owner->id, 'title' => 'Konser Senja', 'slug' => 'konser-senja',
            'pic_name' => 'Panitia', 'pic_identity_type' => 'ktp', 'pic_identity_number' => '3200000000000001',
            'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->toDateString(),
            'verification_status' => 'verified',
        ]);

        // Three seats already sold, so quota reads what is left rather than what it opened with.
        $this->presale = TicketType::create(['event_id' => $this->event->id, 'name' => 'Presale', 'price' => 150000, 'quota' => 97]);
    }

    private function url(string $suffix = ''): string
    {
        return '/api/events/'.$this->event->event_id.'/ticket-types'.$suffix;
    }

    private function vip(array $overrides = []): array
    {
        return array_replace(['name' => 'VIP', 'price' => 250000, 'quota' => 50], $overrides);
    }

    public function test_an_owner_adds_a_tier_to_an_event_that_is_already_selling(): void
    {
        $this->actingAs($this->owner, 'api')->postJson($this->url(), $this->vip(['description' => 'Front standing']))
            ->assertCreated()
            ->assertJsonPath('data.name', 'VIP')
            ->assertJsonPath('data.quota', 50)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('ticket_types', ['event_id' => $this->event->id, 'name' => 'VIP', 'price' => 250000]);
    }

    public function test_the_new_tier_carries_an_id_and_appears_in_my_events(): void
    {
        $id = $this->actingAs($this->owner, 'api')->postJson($this->url(), $this->vip())->json('data.id');

        $this->assertNotNull($id);

        $this->actingAs($this->owner, 'api')->getJson('/api/events/my')->assertOk()
            ->assertJsonPath('data.0.ticket_types.1.id', $id)
            ->assertJsonPath('data.0.ticket_types.1.name', 'VIP');
    }

    public function test_adding_a_tier_leaves_live_inventory_and_verification_alone(): void
    {
        $order = Order::create([
            'order_number' => 'ORD0001', 'buyer_id' => $this->owner->id, 'buyer_name' => 'Budi',
            'event_id' => $this->event->id, 'status' => OrderStatus::Paid, 'total_price' => 450000, 'paid_at' => now(),
        ]);
        $order->items()->create([
            'ticket_type_id' => $this->presale->id, 'ticket_type_name' => 'Presale',
            'unit_price' => 150000, 'quantity' => 3, 'subtotal' => 450000,
        ]);

        $this->actingAs($this->owner, 'api')->postJson($this->url(), $this->vip())->assertCreated();

        // The quota counter is what is left, so a new tier must not disturb it — this is the
        // property the old replace-everything update path could not offer.
        $this->assertSame(97, $this->presale->fresh()->quota);
        $this->assertSame('verified', $this->event->fresh()->verification_status->value);

        $this->actingAs($this->owner, 'api')->getJson('/api/events/'.$this->event->event_id.'/performance')->assertOk()
            ->assertJsonPath('data.ticket_types.0.sold', 3)
            ->assertJsonPath('data.ticket_types.0.remaining', 97)
            ->assertJsonPath('data.ticket_types.0.capacity', 100);
    }

    public function test_another_organizers_event_and_an_unknown_event_answer_the_same_way(): void
    {
        $stranger = User::create(['uid' => 'U000002', 'name' => 'Sari', 'email' => 'sari@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER', 'is_organizer' => true]);

        $this->actingAs($stranger, 'api')->postJson($this->url(), $this->vip())->assertNotFound();
        $this->actingAs($stranger, 'api')->postJson('/api/events/EVT9999/ticket-types', $this->vip())->assertNotFound();

        $this->assertDatabaseMissing('ticket_types', ['name' => 'VIP']);
    }

    public function test_an_account_that_never_activated_eo_access_is_refused(): void
    {
        $buyer = User::create(['uid' => 'U000003', 'name' => 'Rina', 'email' => 'rina@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER']);

        $this->actingAs($buyer, 'api')->postJson($this->url(), $this->vip())->assertForbidden();
    }

    public function test_it_reports_validation_failures_against_the_field_itself(): void
    {
        $this->actingAs($this->owner, 'api')->postJson($this->url(), $this->vip(['quota' => 0]))
            ->assertStatus(422)->assertJsonPath('errors.quota.0', 'The quota field must be at least 1.');

        $this->actingAs($this->owner, 'api')->postJson($this->url(), $this->vip([
            'sale_start' => '2026-10-02 10:00:00', 'sale_end' => '2026-10-01 10:00:00',
        ]))->assertStatus(422)->assertJsonStructure(['errors' => ['sale_end']]);
    }

    public function test_an_update_changes_one_field_at_a_time(): void
    {
        $this->actingAs($this->owner, 'api')->putJson($this->url('/'.$this->presale->id), ['price' => 175000, 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.name', 'Presale');

        $this->assertDatabaseHas('ticket_types', ['id' => $this->presale->id, 'price' => 175000, 'quota' => 97]);
    }

    public function test_half_a_sale_window_is_judged_against_the_half_already_stored(): void
    {
        $this->presale->update(['sale_start' => '2026-10-02 10:00:00']);

        $this->actingAs($this->owner, 'api')->putJson($this->url('/'.$this->presale->id), ['sale_end' => '2026-10-01 10:00:00'])
            ->assertStatus(422)->assertJsonPath('errors.sale_end.0', 'Sales must end after they start.');

        $this->actingAs($this->owner, 'api')->putJson($this->url('/'.$this->presale->id), ['sale_end' => '2026-10-03 10:00:00'])
            ->assertOk();
    }

    public function test_a_tier_somebody_has_ordered_cannot_be_deleted(): void
    {
        $order = Order::create([
            'order_number' => 'ORD0002', 'buyer_id' => $this->owner->id, 'buyer_name' => 'Budi',
            'event_id' => $this->event->id, 'status' => OrderStatus::PendingPayment, 'total_price' => 150000,
        ]);
        $order->items()->create([
            'ticket_type_id' => $this->presale->id, 'ticket_type_name' => 'Presale',
            'unit_price' => 150000, 'quantity' => 1, 'subtotal' => 150000,
        ]);

        $this->actingAs($this->owner, 'api')->deleteJson($this->url('/'.$this->presale->id))->assertStatus(422);

        // The order line references it with a cascading delete, so the refusal is what keeps the
        // buyer's order intact.
        $this->assertDatabaseHas('ticket_types', ['id' => $this->presale->id]);
        $this->assertDatabaseHas('order_items', ['ticket_type_id' => $this->presale->id]);
    }

    public function test_an_unsold_tier_can_be_deleted(): void
    {
        $id = $this->actingAs($this->owner, 'api')->postJson($this->url(), $this->vip())->json('data.id');

        $this->actingAs($this->owner, 'api')->deleteJson($this->url('/'.$id))->assertOk();

        $this->assertDatabaseMissing('ticket_types', ['id' => $id]);
        $this->assertDatabaseHas('ticket_types', ['id' => $this->presale->id]);
    }

    public function test_a_tier_from_another_event_cannot_be_reached_through_this_one(): void
    {
        $other = Event::create([
            'event_id' => 'EVT0002', 'user_id' => $this->owner->id, 'title' => 'Lain', 'slug' => 'lain',
            'pic_name' => 'Panitia', 'pic_identity_type' => 'ktp', 'pic_identity_number' => '3200000000000002',
            'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->toDateString(),
            'verification_status' => 'verified',
        ]);
        $foreign = TicketType::create(['event_id' => $other->id, 'name' => 'Regular', 'price' => 100000, 'quota' => 10]);

        $this->actingAs($this->owner, 'api')->putJson($this->url('/'.$foreign->id), ['price' => 1])->assertNotFound();

        $this->assertDatabaseHas('ticket_types', ['id' => $foreign->id, 'price' => 100000]);
    }

    public function test_a_suspended_event_is_closed_to_ticket_type_changes(): void
    {
        $this->event->update(['verification_status' => 'suspended']);

        $this->actingAs($this->owner, 'api')->postJson($this->url(), $this->vip())->assertStatus(422);
        $this->assertDatabaseMissing('ticket_types', ['name' => 'VIP']);
    }

    public function test_a_pending_event_can_still_have_tiers_added(): void
    {
        $this->event->update(['verification_status' => 'pending']);

        $this->actingAs($this->owner, 'api')->postJson($this->url(), $this->vip())->assertCreated();
    }
}
