<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payout;
use App\Models\PayoutAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawalRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('platform.fee_percent', 5);
        config()->set('platform.minimum_payout', 0);

        $this->organizer = User::create(['uid' => 'U000001', 'name' => 'Andi', 'email' => 'andi@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER', 'is_organizer' => true]);
        $this->event = Event::create([
            'event_id' => 'EVT0001', 'user_id' => $this->organizer->id, 'title' => 'Konser', 'slug' => 'konser',
            'pic_name' => 'P', 'pic_identity_type' => 'ktp', 'pic_identity_number' => '3200000000000001',
            'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->toDateString(),
            'verification_status' => 'verified',
        ]);
        Bank::firstOrCreate(['code' => 'BCA'], ['name' => 'Bank Central Asia', 'is_active' => true]);
    }

    private function sold(string $number, float $total): Order
    {
        return Order::create([
            'order_number' => $number, 'buyer_name' => 'Budi', 'event_id' => $this->event->id,
            'status' => OrderStatus::Paid, 'total_price' => $total, 'paid_at' => now(),
        ]);
    }

    private function withAccount(): PayoutAccount
    {
        return PayoutAccount::create([
            'user_id' => $this->organizer->id, 'bank_code' => 'BCA', 'bank_name' => 'Bank Central Asia',
            'account_number' => '1234567890', 'account_holder' => 'Andi',
        ]);
    }

    public function test_the_balance_is_what_a_payout_would_pay(): void
    {
        $this->sold('ORD0001', 1_000_000);

        $this->actingAs($this->organizer, 'api')->getJson('/api/payouts/balance')->assertOk()
            ->assertJsonPath('data.totals.gross_amount', 1000000)
            ->assertJsonPath('data.totals.platform_fee_amount', 50000)
            ->assertJsonPath('data.totals.net_amount', 950000);
    }

    public function test_the_balance_excludes_money_already_on_a_payout(): void
    {
        $this->sold('ORD0001', 1_000_000);
        $this->withAccount();

        $this->actingAs($this->organizer, 'api')->postJson('/api/payouts/request')->assertCreated();

        // The same money must not appear as available a second time.
        $this->actingAs($this->organizer, 'api')->getJson('/api/payouts/balance')->assertOk()
            ->assertJsonPath('data.totals.net_amount', 0)
            ->assertJsonPath('data.can_request', false);
    }

    public function test_requesting_creates_an_organizer_originated_payout_with_the_bank_snapshot(): void
    {
        $this->sold('ORD0001', 1_000_000);
        $account = $this->withAccount();

        $this->actingAs($this->organizer, 'api')->postJson('/api/payouts/request')->assertCreated()
            ->assertJsonPath('data.source', 'organizer')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.net_amount', 950000)
            ->assertJsonPath('data.account_number', '1234567890');

        // Editing the account later must not rewrite where this payout was sent.
        $account->update(['account_number' => '9999999999']);

        $this->assertSame('1234567890', Payout::first()->account_number);
        $this->assertSame(1, AuditLog::where('action', 'payout.requested')->count());
    }

    public function test_it_refuses_without_a_bank_account(): void
    {
        $this->sold('ORD0001', 1_000_000);

        $this->actingAs($this->organizer, 'api')->getJson('/api/payouts/balance')->assertOk()
            ->assertJsonPath('data.can_request', false)
            ->assertJsonPath('data.blocked_reason', 'Add your bank account before requesting a withdrawal.');

        // The button state and the endpoint must agree.
        $this->actingAs($this->organizer, 'api')->postJson('/api/payouts/request')->assertStatus(422);
        $this->assertSame(0, Payout::count());
    }

    public function test_it_refuses_with_nothing_available(): void
    {
        $this->withAccount();

        $this->actingAs($this->organizer, 'api')->postJson('/api/payouts/request')->assertStatus(422);
        $this->assertSame(0, Payout::count());
    }

    public function test_it_refuses_below_the_configured_minimum(): void
    {
        config()->set('platform.minimum_payout', 5_000_000);
        $this->sold('ORD0001', 1_000_000);
        $this->withAccount();

        $this->actingAs($this->organizer, 'api')->getJson('/api/payouts/balance')->assertOk()
            ->assertJsonPath('data.can_request', false);

        $this->actingAs($this->organizer, 'api')->postJson('/api/payouts/request')->assertStatus(422);
    }

    public function test_it_refuses_a_second_request_while_one_is_in_progress(): void
    {
        $this->sold('ORD0001', 1_000_000);
        $this->withAccount();

        $this->actingAs($this->organizer, 'api')->postJson('/api/payouts/request')->assertCreated();

        $this->sold('ORD0002', 500_000);

        // New sales do not justify a second concurrent request.
        $this->actingAs($this->organizer, 'api')->postJson('/api/payouts/request')->assertStatus(422);
        $this->assertSame(1, Payout::count());
    }

    public function test_a_new_request_is_allowed_once_the_previous_one_is_paid(): void
    {
        $this->sold('ORD0001', 1_000_000);
        $this->withAccount();

        $this->actingAs($this->organizer, 'api')->postJson('/api/payouts/request')->assertCreated();
        $payout = Payout::first();
        $payout->update(['status' => PayoutStatus::Paid]);

        $this->sold('ORD0002', 500_000);

        $this->actingAs($this->organizer, 'api')->postJson('/api/payouts/request')->assertCreated()
            ->assertJsonPath('data.net_amount', 475000);
    }

    public function test_an_organizer_manages_only_their_own_account(): void
    {
        $rival = User::create(['uid' => 'U000009', 'name' => 'Rival', 'email' => 'rival@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER', 'is_organizer' => true]);
        $this->withAccount();

        $this->actingAs($rival, 'api')->getJson('/api/payout-account')->assertOk()->assertJsonPath('data', null);

        $this->actingAs($rival, 'api')->putJson('/api/payout-account', [
            'bank_code' => 'BCA', 'account_number' => '5555', 'account_holder' => 'Rival',
        ])->assertOk();

        // Two accounts, each scoped to its owner.
        $this->assertSame('1234567890', PayoutAccount::where('user_id', $this->organizer->id)->first()->account_number);
        $this->assertSame('5555', PayoutAccount::where('user_id', $rival->id)->first()->account_number);
    }

    public function test_it_rejects_an_unknown_bank(): void
    {
        $this->actingAs($this->organizer, 'api')->putJson('/api/payout-account', [
            'bank_code' => 'NOPE', 'account_number' => '1234', 'account_holder' => 'Andi',
        ])->assertStatus(422)->assertJsonValidationErrors('bank_code');
    }
}
