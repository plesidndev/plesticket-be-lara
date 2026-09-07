<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrganizerMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentSalesReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_revenue_counts_each_paid_order_once_with_multiple_ticket_types(): void
    {
        $owner = User::factory()->create(['is_organizer' => true, 'is_plesconnect_user' => true]);
        $event = $this->actingAs($owner->fresh(), 'api')->postJson('/api/events', [
            'title' => 'Agent Report Event', 'pic_name' => 'Owner', 'pic_identity_type' => 'ktp', 'pic_identity_number' => '001234',
            'start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(11)->toDateString(),
            'ticket_types' => [['name' => 'General', 'price' => 10000, 'quota' => 100], ['name' => 'Second', 'price' => 10000, 'quota' => 100]],
        ])->assertCreated()->json('data');
        $agent = OrganizerMember::create(['uid' => 'AGENT001', 'owner_id' => $owner->id, 'event_id' => $event['id'],
            'name' => 'Agent One', 'password' => 'password123', 'role' => 'MITRA_TICKET_BOX', 'commission_rate' => 10, 'is_active' => true]);
        foreach (['paid', 'paid', 'pending_payment'] as $index => $status) {
            $order = Order::create(['order_number' => 'ORDTEST'.$index, 'event_id' => $event['id'], 'agent_id' => $agent->id,
                'is_agent_sale' => true, 'buyer_name' => 'Buyer', 'status' => $status, 'total_price' => 30000]);
            foreach ($event['ticket_types'] as $ticketIndex => $ticket) {
                $order->items()->create(['ticket_type_id' => $ticket['id'], 'ticket_type_name' => $ticket['name'],
                    'unit_price' => 10000, 'quantity' => $ticketIndex + 1, 'subtotal' => 10000 * ($ticketIndex + 1)]);
            }
        }

        $this->getJson('/api/events/'.$event['id'].'/agents/summary')->assertOk()
            ->assertJsonPath('data.totals.total_orders', 2)->assertJsonPath('data.totals.total_tickets_sold', 6)
            ->assertJsonPath('data.totals.total_revenue', 60000)->assertJsonPath('data.totals.total_commission_owed', 6000);
        $this->getJson('/api/events/'.$event['id'].'/agents/'.$agent->id.'/summary')->assertOk()
            ->assertJsonPath('data.total_revenue', 60000)->assertJsonPath('data.commission_owed', 6000);
    }
}
