<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Models\Order;
use App\Models\Ticket;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\TicketRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface  $orders,
        private readonly TicketRepositoryInterface $tickets,
        private readonly EventRepositoryInterface  $events,
    ) {}

    public function listByBuyer(int $buyerId, int $perPage): LengthAwarePaginator
    {
        return $this->orders->paginateByBuyer($buyerId, $perPage);
    }

    public function listByAgent(int $agentId, int $perPage, ?string $search): LengthAwarePaginator
    {
        return $this->orders->paginateByAgent($agentId, $perPage, $search);
    }

    public function listAgentOrdersByEvent(string $eventId, int $perPage, ?string $search): LengthAwarePaginator
    {
        return $this->orders->paginateAgentOrdersByEvent($eventId, $perPage, $search);
    }

    public function agentsSummaryByEvent(string $eventId): array
    {
        $agents = $this->orders->agentsSummaryByEvent($eventId);

        return [
            'totals' => [
                'total_orders'        => array_sum(array_column($agents, 'total_orders')),
                'total_tickets_sold'  => array_sum(array_column($agents, 'total_tickets_sold')),
                'total_revenue'       => array_sum(array_column($agents, 'total_revenue')),
                'total_commission_owed' => array_sum(array_column($agents, 'commission_owed')),
            ],
            'agents' => $agents,
        ];
    }

    public function findByOrderNumberForAgent(string $orderNumber, int $agentId): Order
    {
        $order = $this->orders->findByOrderNumber($orderNumber);

        if (! $order || $order->agent_id !== $agentId) {
            throw new RuntimeException('Order not found.');
        }

        return $order;
    }

    public function findByOrderNumber(string $orderNumber, int $buyerId): Order
    {
        $order = $this->orders->findByOrderNumber($orderNumber);

        if (! $order || $order->buyer_id !== $buyerId) {
            throw new RuntimeException('Order not found.');
        }

        return $order;
    }

    public function create(int $buyerId, array $data): Order
    {
        $event = $this->events->findById($data['event_id']);

        if (! $event) {
            throw new RuntimeException('Event not found.');
        }

        if ($event->verification_status->value !== 'verified') {
            throw new InvalidArgumentException('Event is not available for purchase.');
        }

        $now   = now();
        $items = $data['items'];
        $lines = [];
        $total = 0;

        foreach ($items as $item) {
            $ticketType = $event->ticketTypes->firstWhere('id', $item['ticket_type_id']);

            if (! $ticketType || ! $ticketType->is_active) {
                throw new InvalidArgumentException("Ticket type #{$item['ticket_type_id']} is not available.");
            }

            if ($ticketType->sale_start && $ticketType->sale_start->gt($now)) {
                throw new InvalidArgumentException("Ticket type \"{$ticketType->name}\" is not on sale yet.");
            }

            if ($ticketType->sale_end && $ticketType->sale_end->lt($now)) {
                throw new InvalidArgumentException("Ticket type \"{$ticketType->name}\" sale has ended.");
            }

            $qty = (int) $item['quantity'];

            if ($ticketType->quota < $qty) {
                throw new InvalidArgumentException("Not enough quota for \"{$ticketType->name}\". Available: {$ticketType->quota}.");
            }

            $subtotal = $ticketType->price * $qty;
            $total   += $subtotal;

            $lines[] = [
                'ticket_type'    => $ticketType,
                'quantity'       => $qty,
                'unit_price'     => $ticketType->price,
                'subtotal'       => $subtotal,
            ];
        }

        // Decrement quotas
        foreach ($lines as $line) {
            $line['ticket_type']->decrement('quota', $line['quantity']);
        }

        $order = $this->orders->create([
            'buyer_id'    => $buyerId,
            'event_id'    => $event->id,
            'status'      => OrderStatus::PendingPayment,
            'total_price' => $total,
            'expires_at'  => now()->addMinutes(30),
        ]);

        foreach ($lines as $line) {
            $order->items()->create([
                'ticket_type_id'   => $line['ticket_type']->id,
                'ticket_type_name' => $line['ticket_type']->name,
                'unit_price'       => $line['unit_price'],
                'quantity'         => $line['quantity'],
                'subtotal'         => $line['subtotal'],
            ]);
        }

        return $order->fresh(['event', 'items.ticketType']);
    }

    public function pay(string $orderNumber, int $buyerId, ?string $paymentMethod): Order
    {
        $order = $this->findByOrderNumber($orderNumber, $buyerId);

        $this->assertPayable($order);

        return $this->markPaid($order, $paymentMethod);
    }

    /**
     * Guards that an order can still be paid, expiring it (and releasing its
     * quota) if the 30-minute hold has lapsed.
     *
     * @throws InvalidArgumentException
     */
    public function assertPayable(Order $order): void
    {
        if ($order->status !== OrderStatus::PendingPayment) {
            throw new InvalidArgumentException('Only pending orders can be paid.');
        }

        if ($order->isExpired()) {
            $this->expire($order);
            throw new InvalidArgumentException('Order has expired. Please create a new order.');
        }
    }

    /**
     * Settles an order and issues its tickets.
     *
     * Called from two places: the buyer paying directly, and a payment gateway
     * webhook confirming a charge. Idempotent — a provider that retries a
     * callback must not mint a second set of tickets — and wrapped in a
     * transaction so a failure mid-issuance cannot leave an order marked paid
     * with only some of its tickets created.
     */
    public function markPaid(Order $order, ?string $paymentMethod, ?\DateTimeInterface $paidAt = null): Order
    {
        if ($order->status === OrderStatus::Paid) {
            return $this->orders->findByOrderNumber($order->order_number);
        }

        DB::transaction(function () use ($order, $paymentMethod, $paidAt) {
            // Re-read under a row lock: two webhook deliveries arriving together
            // would otherwise both pass the status check above.
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $locked || $locked->status === OrderStatus::Paid) {
                return;
            }

            $this->orders->update($locked, [
                'status'         => OrderStatus::Paid,
                'payment_method' => $paymentMethod ?? $locked->payment_method,
                'paid_at'        => $paidAt ?? now(),
            ]);

            $locked->refresh();
            $holderName = $locked->buyer?->name ?? $locked->buyer_name;

            foreach ($locked->items as $item) {
                for ($i = 0; $i < $item->quantity; $i++) {
                    $this->tickets->create([
                        'ticket_code'    => $this->generateTicketCode(),
                        'order_id'       => $locked->id,
                        'order_item_id'  => $item->id,
                        'ticket_type_id' => $item->ticket_type_id,
                        'event_id'       => $locked->event_id,
                        'buyer_id'       => $locked->buyer_id,
                        'holder_name'    => $holderName,
                        'status'         => TicketStatus::Active,
                    ]);
                }
            }
        });

        return $this->orders->findByOrderNumber($order->order_number);
    }

    /**
     * Marks an order expired and returns its reserved quota to the pool.
     *
     * Row-locked: the sweeper runs alongside live requests that also expire
     * lapsed orders, and restoring the same order's quota twice would hand out
     * seats that do not exist.
     *
     * Returns true only when this call is the one that released the order.
     */
    public function expire(Order $order): bool
    {
        return (bool) DB::transaction(function () use ($order) {
            $locked = Order::with('items.ticketType')
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->status !== OrderStatus::PendingPayment) {
                return false;
            }

            $this->restoreQuotas($locked);
            $this->orders->update($locked, ['status' => OrderStatus::Expired]);

            return true;
        });
    }

    public function cancel(string $orderNumber, int $buyerId): Order
    {
        $order = $this->findByOrderNumber($orderNumber, $buyerId);

        if ($order->status !== OrderStatus::PendingPayment) {
            throw new InvalidArgumentException('Only pending orders can be cancelled.');
        }

        $this->restoreQuotas($order);

        return $this->orders->update($order, ['status' => OrderStatus::Cancelled]);
    }

    public function getTicket(string $code): Ticket
    {
        $ticket = $this->tickets->findByCode($code);

        if (! $ticket) {
            throw new RuntimeException('Ticket not found.');
        }

        return $ticket;
    }

    public function scanTicket(string $code, int $memberId, string $memberEventId): Ticket
    {
        $ticket = $this->getTicket($code);

        // Validate the gate officer belongs to this event
        if ($ticket->event_id !== $memberEventId) {
            throw new RuntimeException('Ticket does not belong to your event.');
        }

        if ($ticket->status === TicketStatus::Used) {
            throw new InvalidArgumentException('Ticket has already been scanned.');
        }

        if ($ticket->status === TicketStatus::Cancelled) {
            throw new InvalidArgumentException('Ticket is cancelled.');
        }

        return $this->tickets->update($ticket, [
            'status'     => TicketStatus::Used,
            'scanned_at' => now(),
            'scanned_by' => $memberId,
        ]);
    }

    public function createAgentOrder(int $agentId, array $data): Order
    {
        $event = $this->events->findById($data['event_id']);

        if (! $event) {
            throw new RuntimeException('Event not found.');
        }

        if ($event->verification_status->value !== 'verified') {
            throw new InvalidArgumentException('Event is not available for purchase.');
        }

        $now   = now();
        $items = $data['items'];
        $lines = [];
        $total = 0;

        foreach ($items as $item) {
            $ticketType = $event->ticketTypes->firstWhere('id', $item['ticket_type_id']);

            if (! $ticketType || ! $ticketType->is_active) {
                throw new InvalidArgumentException("Ticket type #{$item['ticket_type_id']} is not available.");
            }

            if ($ticketType->sale_start && $ticketType->sale_start->gt($now)) {
                throw new InvalidArgumentException("Ticket type \"{$ticketType->name}\" is not on sale yet.");
            }

            if ($ticketType->sale_end && $ticketType->sale_end->lt($now)) {
                throw new InvalidArgumentException("Ticket type \"{$ticketType->name}\" sale has ended.");
            }

            $qty = (int) $item['quantity'];

            if ($ticketType->quota < $qty) {
                throw new InvalidArgumentException("Not enough quota for \"{$ticketType->name}\". Available: {$ticketType->quota}.");
            }

            $subtotal = $ticketType->price * $qty;
            $total   += $subtotal;

            $lines[] = [
                'ticket_type' => $ticketType,
                'quantity'    => $qty,
                'unit_price'  => $ticketType->price,
                'subtotal'    => $subtotal,
            ];
        }

        foreach ($lines as $line) {
            $line['ticket_type']->decrement('quota', $line['quantity']);
        }

        $order = $this->orders->create([
            'agent_id'       => $agentId,
            'is_agent_sale'  => true,
            'buyer_name'     => $data['buyer_name'],
            'buyer_phone'    => $data['buyer_phone'],
            'event_id'       => $event->id,
            'status'         => \App\Enums\OrderStatus::Paid,
            'total_price'    => $total,
            'payment_method' => 'cash',
            'paid_at'        => now(),
        ]);

        foreach ($lines as $line) {
            $order->items()->create([
                'ticket_type_id'   => $line['ticket_type']->id,
                'ticket_type_name' => $line['ticket_type']->name,
                'unit_price'       => $line['unit_price'],
                'quantity'         => $line['quantity'],
                'subtotal'         => $line['subtotal'],
            ]);
        }

        $order->refresh();

        foreach ($order->items as $item) {
            for ($i = 0; $i < $item->quantity; $i++) {
                $this->tickets->create([
                    'ticket_code'    => $this->generateTicketCode(),
                    'order_id'       => $order->id,
                    'order_item_id'  => $item->id,
                    'ticket_type_id' => $item->ticket_type_id,
                    'event_id'       => $order->event_id,
                    'holder_name'    => $data['buyer_name'],
                    'status'         => TicketStatus::Active,
                ]);
            }
        }

        return $order->fresh(['event', 'items.tickets']);
    }

    public function agentSummary(int $agentId, float $commissionRate, ?string $from, ?string $to): array
    {
        $summary = $this->orders->agentSummary($agentId, $from, $to);

        $summary['commission_rate']   = $commissionRate;
        $summary['commission_earned'] = round($summary['total_revenue'] * $commissionRate / 100, 2);
        $summary['period']            = ['from' => $from, 'to' => $to];

        return $summary;
    }

    private function restoreQuotas(Order $order): void
    {
        foreach ($order->items as $item) {
            $item->ticketType?->increment('quota', $item->quantity);
        }
    }

    private function generateTicketCode(): string
    {
        do {
            $code = strtoupper(Str::random(12));
        } while (\App\Models\Ticket::where('ticket_code', $code)->exists());

        return $code;
    }
}
