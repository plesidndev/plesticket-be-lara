<?php

namespace App\Repositories;

use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderRepository implements OrderRepositoryInterface
{
    public function paginateByBuyer(int $buyerId, int $perPage): LengthAwarePaginator
    {
        return Order::with(['event', 'items'])
            ->where('buyer_id', $buyerId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findByOrderNumber(string $orderNumber): ?Order
    {
        return Order::with(['event', 'items.tickets', 'items.ticketType'])
            ->where('order_number', $orderNumber)
            ->first();
    }

    public function create(array $data): Order
    {
        $order = Order::create($data);
        $order->update(['order_number' => sprintf('ORD%s%05d', now()->format('Ymd'), Order::count())]);
        return $order->fresh(['event', 'items']);
    }

    public function update(Order $order, array $data): Order
    {
        $order->update($data);
        return $order->fresh(['event', 'items.tickets', 'items.ticketType']);
    }
}
