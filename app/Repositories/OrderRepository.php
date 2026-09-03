<?php

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderRepository implements OrderRepositoryInterface
{
    public function paginateByBuyer(int $buyerId, int $perPage): LengthAwarePaginator
    {
        return Order::with(['event', 'items'])
            ->where('buyer_id', $buyerId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function pendingExpired(int $limit): Collection
    {
        return Order::with('items.ticketType')
            ->where('status', OrderStatus::PendingPayment->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->orderBy('expires_at')
            ->limit($limit)
            ->get();
    }

    public function findByOrderNumber(string $orderNumber): ?Order
    {
        return Order::with(['event', 'items.tickets', 'items.ticketType'])
            ->where('order_number', $orderNumber)
            ->first();
    }

    public function paginateByAgent(int $agentId, int $perPage, ?string $search): LengthAwarePaginator
    {
        return Order::with(['event', 'items.tickets'])
            ->where('agent_id', $agentId)
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('buyer_name', 'like', "%{$search}%")
                    ->orWhere('buyer_phone', 'like', "%{$search}%")
                    ->orWhere('order_number', 'like', "%{$search}%");
            }))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function agentSummary(int $agentId, ?string $from, ?string $to): array
    {
        $query = Order::where('agent_id', $agentId)->where('status', 'paid');

        if ($from) {
            $query->whereDate('paid_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('paid_at', '<=', $to);
        }

        $totalOrders = $query->count();
        $totalRevenue = $query->sum('total_price');
        $totalTickets = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.agent_id', $agentId)
            ->where('orders.status', 'paid')
            ->when($from, fn ($q) => $q->whereDate('orders.paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('orders.paid_at', '<=', $to))
            ->sum('order_items.quantity');

        return [
            'total_orders' => $totalOrders,
            'total_tickets_sold' => (int) $totalTickets,
            'total_revenue' => (float) $totalRevenue,
        ];
    }

    public function paginateAgentOrdersByEvent(string $eventId, int $perPage, ?string $search): LengthAwarePaginator
    {
        return Order::with(['agent', 'items'])
            ->where('event_id', $eventId)
            ->where('is_agent_sale', true)
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('buyer_name', 'like', "%{$search}%")
                    ->orWhere('buyer_phone', 'like', "%{$search}%")
                    ->orWhere('order_number', 'like', "%{$search}%");
            }))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function agentsSummaryByEvent(string $eventId): array
    {
        return DB::table('organizer_members')
            ->where('organizer_members.event_id', $eventId)
            ->where('organizer_members.role', 'MITRA_TICKET_BOX')
            ->leftJoin('orders', function ($join) {
                $join->on('orders.agent_id', '=', 'organizer_members.id')
                    ->where('orders.status', '=', 'paid');
            })
            ->leftJoin('order_items', 'order_items.order_id', '=', 'orders.id')
            ->select(
                'organizer_members.id',
                'organizer_members.uid',
                'organizer_members.name',
                'organizer_members.commission_rate',
                'organizer_members.is_active',
                DB::raw('COUNT(DISTINCT orders.id) as total_orders'),
                DB::raw('COALESCE(SUM(order_items.quantity), 0) as total_tickets_sold'),
                DB::raw('COALESCE(SUM(orders.total_price), 0) as total_revenue'),
            )
            ->groupBy(
                'organizer_members.id',
                'organizer_members.uid',
                'organizer_members.name',
                'organizer_members.commission_rate',
                'organizer_members.is_active',
            )
            ->orderBy('organizer_members.name')
            ->get()
            ->map(fn ($row) => [
                'agent' => [
                    'id' => $row->id,
                    'uid' => $row->uid,
                    'name' => $row->name,
                    'commission_rate' => (float) $row->commission_rate,
                    'is_active' => (bool) $row->is_active,
                ],
                'total_orders' => (int) $row->total_orders,
                'total_tickets_sold' => (int) $row->total_tickets_sold,
                'total_revenue' => (float) $row->total_revenue,
                'commission_owed' => round((float) $row->total_revenue * (float) $row->commission_rate / 100, 2),
            ])
            ->toArray();
    }

    public function create(array $data): Order
    {
        $data['order_number'] = sprintf('ORD%s%05d', now()->format('Ymd'), Order::count() + 1);
        $order = Order::create($data);

        return $order->fresh(['event', 'items']);
    }

    public function update(Order $order, array $data): Order
    {
        $order->update($data);

        return $order->fresh(['event', 'items.tickets', 'items.ticketType']);
    }
}
