<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Repositories\OrderRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class OrderNumberFormatTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = User::create([
            'uid' => 'USR0001', 'name' => 'Budi', 'email' => 'budi@example.com',
            'password' => 'secret', 'role' => 'REGISTERED_USER',
        ]);

        $this->event = Event::create([
            'event_id' => 'EVT0001', 'user_id' => $this->buyer->id, 'title' => 'Konser Senja',
            'slug' => 'konser-senja', 'pic_name' => 'Panitia', 'pic_identity_type' => 'ktp',
            'pic_identity_number' => '3200000000000001',
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'verification_status' => 'verified',
        ]);
    }

    public function test_it_builds_the_number_from_the_prefix_minute_and_a_random_tail(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-08 14:32:45'));

        $number = app(OrderRepository::class)->nextOrderNumber();

        $this->assertMatchesRegularExpression('/^PLES2609081432\d{6}$/', $number);
        $this->assertSame(20, strlen($number), 'must fill the 20-character column exactly');
    }

    public function test_the_minute_is_the_minute_not_the_seconds(): void
    {
        $repository = app(OrderRepository::class);

        Carbon::setTestNow(Carbon::parse('2026-09-08 14:00:30'));
        $first = substr($repository->nextOrderNumber(), 0, 14);

        Carbon::setTestNow(Carbon::parse('2026-09-08 14:01:30'));
        $second = substr($repository->nextOrderNumber(), 0, 14);

        // A format built from hours and seconds would collapse these two onto the same prefix.
        $this->assertNotSame($first, $second);
        $this->assertSame('PLES2609081400', $first);
        $this->assertSame('PLES2609081401', $second);
    }

    public function test_it_assigns_the_new_format_when_creating_an_order(): void
    {
        $order = app(OrderRepository::class)->create($this->orderAttributes());

        $this->assertMatchesRegularExpression('/^PLES\d{16}$/', $order->order_number);
    }

    public function test_the_tail_does_not_disclose_how_many_orders_exist(): void
    {
        $repository = app(OrderRepository::class);
        Carbon::setTestNow(Carbon::parse('2026-09-08 14:32:00'));

        $numbers = [];

        for ($i = 0; $i < 25; $i++) {
            $numbers[] = $repository->create($this->orderAttributes())->order_number;
        }

        $this->assertCount(25, array_unique($numbers), 'numbers must be unique within a minute');

        // The old ORD<date><count> scheme made the tail the row count; nothing should track it now.
        $tails = array_map(static fn (string $n): string => substr($n, -6), $numbers);
        $this->assertNotSame(range(1, 25), array_map('intval', $tails));
    }

    public function test_it_retries_when_a_number_is_already_taken(): void
    {
        $taken = 'PLES2609081432000001';
        Order::create($this->orderAttributes(['order_number' => $taken]));

        $repository = Mockery::mock(OrderRepository::class)->makePartial();
        $repository->shouldReceive('nextOrderNumber')->times(3)
            ->andReturn($taken, $taken, 'PLES2609081432999999');

        $order = $repository->create($this->orderAttributes());

        $this->assertSame('PLES2609081432999999', $order->order_number);
        $this->assertSame(2, Order::count());
    }

    public function test_it_gives_up_after_the_attempt_limit(): void
    {
        $taken = 'PLES2609081432000001';
        Order::create($this->orderAttributes(['order_number' => $taken]));

        $repository = Mockery::mock(OrderRepository::class)->makePartial();
        $repository->shouldReceive('nextOrderNumber')->andReturn($taken);

        $this->expectException(UniqueConstraintViolationException::class);

        $repository->create($this->orderAttributes());
    }

    /** @param array<string, mixed> $overrides */
    private function orderAttributes(array $overrides = []): array
    {
        return array_merge([
            'buyer_id' => $this->buyer->id,
            'event_id' => $this->event->id,
            'status' => OrderStatus::PendingPayment,
            'total_price' => 150000,
            'buyer_name' => 'Budi',
            'buyer_phone' => '08123',
        ], $overrides);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
