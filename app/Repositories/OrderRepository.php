<?php

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderRepository implements OrderRepositoryInterface
{
    private const NUMBER_PREFIX = 'PLES';

    /** Attempts before a collision is allowed to surface; six digits make a second draw unlikely. */
    private const NUMBER_ATTEMPTS = 5;

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
            ->leftJoinSub(
                DB::table('order_items')->selectRaw('order_id, SUM(quantity) as quantity')->groupBy('order_id'),
                'order_items',
                fn ($join) => $join->on('order_items.order_id', '=', 'orders.id'),
            )
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

    /**
     * Console-wide order listing. Unlike the buyer, agent and event queries above this is scoped to
     * nobody, so every filter is optional and the caller decides how narrow the view should be.
     *
     * @param  array{status?: string, event_id?: string, requires_refund?: bool, from?: string, to?: string, search?: string}  $filters
     */
    public function paginateForConsole(int $perPage, array $filters = []): LengthAwarePaginator
    {
        $query = Order::query()->with(['event', 'buyer'])->withCount('items')->latest('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['event_id'])) {
            $query->where('event_id', $filters['event_id']);
        }

        if (! empty($filters['buyer_uid'])) {
            $query->whereHas('buyer', fn ($buyer) => $buyer->where('uid', $filters['buyer_uid']));
        }

        if (! empty($filters['requires_refund'])) {
            $query->whereHas('payments', fn ($payment) => $payment->where('requires_refund', true));
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($scope) use ($search) {
                $scope->where('order_number', 'like', '%'.$search.'%')
                    ->orWhere('buyer_name', 'like', '%'.$search.'%')
                    ->orWhere('buyer_phone', 'like', '%'.$search.'%');
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Create an order, retrying if the generated number is already taken.
     *
     * A collision needs two orders in the same minute drawing the same six digits, so this loop is
     * a safety net rather than a routine path. It is still required: `order_number` is unique, and
     * without it a collision would surface as a failed checkout that nobody could reproduce.
     */
    /**
     * How one event is performing.
     *
     * `ticket_types.quota` holds what is *left*, not the original capacity: it is decremented when
     * an order is placed and restored when that order is cancelled or expires. Capacity is therefore
     * derived as remaining + the quantities still holding quota, which is exactly the pending and
     * paid orders. Cancelled and expired orders have already given their seats back and must not
     * be counted, or capacity would inflate every time somebody abandoned a checkout.
     *
     * @return array{ticket_types: list<array<string, mixed>>, totals: array<string, mixed>}
     */
    public function eventPerformance(string $eventId): array
    {
        $holdingStatuses = ['pending_payment', 'paid'];

        $perType = DB::table('ticket_types')
            ->where('ticket_types.event_id', $eventId)
            ->leftJoin('order_items', 'order_items.ticket_type_id', '=', 'ticket_types.id')
            ->leftJoin('orders', 'orders.id', '=', 'order_items.order_id')
            ->select(
                'ticket_types.id',
                'ticket_types.name',
                'ticket_types.price',
                'ticket_types.quota',
                DB::raw("COALESCE(SUM(CASE WHEN orders.status = 'paid' THEN order_items.quantity ELSE 0 END), 0) as sold"),
                DB::raw("COALESCE(SUM(CASE WHEN orders.status = 'pending_payment' THEN order_items.quantity ELSE 0 END), 0) as held"),
                DB::raw("COALESCE(SUM(CASE WHEN orders.status = 'paid' THEN order_items.subtotal ELSE 0 END), 0) as revenue"),
            )
            ->groupBy('ticket_types.id', 'ticket_types.name', 'ticket_types.price', 'ticket_types.quota')
            ->orderBy('ticket_types.id')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'price' => (float) $row->price,
                'remaining' => (int) $row->quota,
                'sold' => (int) $row->sold,
                'held' => (int) $row->held,
                'capacity' => (int) $row->quota + (int) $row->sold + (int) $row->held,
                'revenue' => (float) $row->revenue,
            ])
            ->all();

        $orders = DB::table('orders')
            ->where('event_id', $eventId)
            ->selectRaw('status, COUNT(*) as orders, COALESCE(SUM(total_price), 0) as revenue')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $tickets = DB::table('tickets')
            ->where('event_id', $eventId)
            ->selectRaw('COUNT(*) as issued, COUNT(scanned_at) as scanned')
            ->first();

        $refunds = DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('orders.event_id', $eventId)
            ->where('payments.requires_refund', true)
            ->count();

        $issued = (int) ($tickets->issued ?? 0);
        $scanned = (int) ($tickets->scanned ?? 0);

        return [
            'ticket_types' => $perType,
            'totals' => [
                'capacity' => array_sum(array_column($perType, 'capacity')),
                'sold' => array_sum(array_column($perType, 'sold')),
                'held' => array_sum(array_column($perType, 'held')),
                'remaining' => array_sum(array_column($perType, 'remaining')),
                'gross_revenue' => (float) ($orders['paid']->revenue ?? 0),
                'orders_paid' => (int) ($orders['paid']->orders ?? 0),
                'orders_pending' => (int) ($orders['pending_payment']->orders ?? 0),
                'orders_cancelled' => (int) ($orders['cancelled']->orders ?? 0),
                'orders_expired' => (int) ($orders['expired']->orders ?? 0),
                'refunds_flagged' => $refunds,
                'tickets_issued' => $issued,
                'tickets_scanned' => $scanned,
                'check_in_rate' => $issued > 0 ? round($scanned / $issued * 100, 1) : 0.0,
            ],
        ];
    }

    public function create(array $data): Order
    {
        for ($attempt = 1; ; $attempt++) {
            $data['order_number'] = $this->nextOrderNumber();

            try {
                $order = Order::create($data);

                break;
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= self::NUMBER_ATTEMPTS) {
                    throw $exception;
                }
            }
        }

        return $order->fresh(['event', 'items']);
    }

    /**
     * PLES + YYMMDDhhmm + six random digits, filling the column's 20 characters exactly.
     *
     * The tail is random rather than sequential so a receipt does not disclose how many orders
     * the platform has taken, nor let the holder guess a neighbouring order. random_int() rather
     * than rand() for the same reason: a predictable tail would move the problem, not remove it.
     */
    public function nextOrderNumber(): string
    {
        return sprintf('%s%s%06d', self::NUMBER_PREFIX, now()->format('ymdHi'), random_int(0, 999999));
    }

    public function update(Order $order, array $data): Order
    {
        $order->update($data);

        return $order->fresh(['event', 'items.tickets', 'items.ticketType']);
    }
}
