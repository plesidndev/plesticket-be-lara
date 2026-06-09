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

        if ($order->status !== OrderStatus::PendingPayment) {
            throw new InvalidArgumentException('Only pending orders can be paid.');
        }

        if ($order->isExpired()) {
            $this->restoreQuotas($order);
            $this->orders->update($order, ['status' => OrderStatus::Expired]);
            throw new InvalidArgumentException('Order has expired. Please create a new order.');
        }

        $this->orders->update($order, [
            'status'         => OrderStatus::Paid,
            'payment_method' => $paymentMethod,
            'paid_at'        => now(),
        ]);

        $order->refresh();
        $buyer = $order->buyer;

        foreach ($order->items as $item) {
            for ($i = 0; $i < $item->quantity; $i++) {
                $this->tickets->create([
                    'ticket_code'    => $this->generateTicketCode(),
                    'order_id'       => $order->id,
                    'order_item_id'  => $item->id,
                    'ticket_type_id' => $item->ticket_type_id,
                    'event_id'       => $order->event_id,
                    'buyer_id'       => $buyerId,
                    'holder_name'    => $buyer->name,
                    'status'         => TicketStatus::Active,
                ]);
            }
        }

        return $this->orders->findByOrderNumber($orderNumber);
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
