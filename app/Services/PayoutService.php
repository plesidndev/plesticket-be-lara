<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Models\Order;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * What the platform owes an organizer, and the record of paying it.
 *
 * Deductions, as decided for this platform:
 *   - platform fee: a percentage of gross, per event (events.platform_fee_percent) falling back to
 *     config('platform.fee_percent');
 *   - agent commission: the selling agent's rate applied to that order's gross;
 *   - payment-provider fees are NOT deducted — the platform absorbs them out of its own share.
 *
 * An order is payable once, and only once. The uniqueness of payout_lines.order_id is what
 * guarantees that under concurrency; this service simply never offers an already-paid order again.
 */
class PayoutService
{
    /**
     * Orders eligible for payout: paid, inside the window, belonging to this organizer's events,
     * not already on a payout, and not flagged for refund.
     *
     * A refund-flagged order is deliberately withheld rather than netted off — the money is going
     * back to the buyer, so paying it out and clawing it back later would be worse than waiting.
     */
    private function payableOrders(int $organizerId, string $from, string $to)
    {
        return Order::query()
            ->with(['event', 'agent'])
            ->whereHas('event', fn ($event) => $event->where('user_id', $organizerId))
            ->where('status', OrderStatus::Paid)
            ->whereDate('paid_at', '>=', $from)
            ->whereDate('paid_at', '<=', $to)
            ->whereDoesntHave('payments', fn ($payment) => $payment->where('requires_refund', true))
            ->whereNotExists(fn ($query) => $query->selectRaw(1)->from('payout_lines')->whereColumn('payout_lines.order_id', 'orders.id'))
            ->orderBy('paid_at')
            ->get();
    }

    /**
     * What a payout would contain, without creating one.
     *
     * @return array{lines: list<array<string, mixed>>, totals: array<string, float|int>}
     */
    public function preview(int $organizerId, string $from, string $to): array
    {
        $lines = [];
        $gross = $platformFee = $agentCommission = 0.0;

        foreach ($this->payableOrders($organizerId, $from, $to) as $order) {
            $orderGross = (float) $order->total_price;
            $feePercent = $order->event?->platform_fee_percent !== null
                ? (float) $order->event->platform_fee_percent
                : (float) config('platform.fee_percent');

            $orderFee = round($orderGross * $feePercent / 100, 2);
            $orderCommission = $order->agent
                ? round($orderGross * (float) $order->agent->commission_rate / 100, 2)
                : 0.0;

            $lines[] = [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'paid_at' => $order->paid_at?->toISOString(),
                'gross_amount' => $orderGross,
                'platform_fee_percent' => $feePercent,
                'platform_fee_amount' => $orderFee,
                'agent_commission_amount' => $orderCommission,
                'net_amount' => round($orderGross - $orderFee - $orderCommission, 2),
            ];

            $gross += $orderGross;
            $platformFee += $orderFee;
            $agentCommission += $orderCommission;
        }

        return [
            'lines' => $lines,
            'totals' => [
                'orders' => count($lines),
                'gross_amount' => round($gross, 2),
                'platform_fee_amount' => round($platformFee, 2),
                'agent_commission_amount' => round($agentCommission, 2),
                'net_amount' => round($gross - $platformFee - $agentCommission, 2),
            ],
        ];
    }

    public function create(int $organizerId, string $from, string $to): Payout
    {
        $organizer = User::find($organizerId);

        if (! $organizer || ! $organizer->is_organizer) {
            throw new RuntimeException('Organizer not found.');
        }

        return DB::transaction(function () use ($organizer, $from, $to): Payout {
            $preview = $this->preview($organizer->id, $from, $to);

            if ($preview['lines'] === []) {
                throw new InvalidArgumentException('There is nothing to pay out for this period.');
            }

            $payout = Payout::create([
                'reference' => 'PO'.now()->format('Ymd').Str::upper(Str::random(6)),
                'organizer_id' => $organizer->id,
                'period_start' => $from,
                'period_end' => $to,
                'gross_amount' => $preview['totals']['gross_amount'],
                'platform_fee_amount' => $preview['totals']['platform_fee_amount'],
                'agent_commission_amount' => $preview['totals']['agent_commission_amount'],
                'net_amount' => $preview['totals']['net_amount'],
                'status' => PayoutStatus::Pending,
            ]);

            foreach ($preview['lines'] as $line) {
                $payout->lines()->create([
                    'order_id' => $line['order_id'],
                    'order_number' => $line['order_number'],
                    'gross_amount' => $line['gross_amount'],
                    'platform_fee_amount' => $line['platform_fee_amount'],
                    'agent_commission_amount' => $line['agent_commission_amount'],
                    'net_amount' => $line['net_amount'],
                ]);
            }

            return $payout->fresh('lines');
        });
    }

    public function approve(string $id, int $approverId): Payout
    {
        $payout = $this->find($id);

        if ($payout->status !== PayoutStatus::Pending) {
            throw new InvalidArgumentException('Only a pending payout can be approved.');
        }

        $payout->update(['status' => PayoutStatus::Approved, 'approved_by' => $approverId, 'approved_at' => now()]);

        return $payout->fresh();
    }

    /**
     * Record that the transfer happened. This does not move money — the bank does, and the
     * reference is how someone later proves which transfer settled this payout.
     */
    public function markPaid(string $id, int $payerId, string $bankReference): Payout
    {
        $payout = $this->find($id);

        if ($payout->status !== PayoutStatus::Approved) {
            throw new InvalidArgumentException('Only an approved payout can be marked paid.');
        }

        $payout->update(['status' => PayoutStatus::Paid, 'paid_by' => $payerId, 'paid_at' => now(), 'bank_reference' => $bankReference]);

        return $payout->fresh();
    }

    /** Cancelling releases the orders so a corrected payout can pick them up. */
    public function cancel(string $id, ?string $note = null): Payout
    {
        $payout = $this->find($id);

        if ($payout->status->isFinal()) {
            throw new InvalidArgumentException('A settled payout cannot be cancelled.');
        }

        return DB::transaction(function () use ($payout, $note): Payout {
            $payout->lines()->delete();
            $payout->update(['status' => PayoutStatus::Cancelled, 'note' => $note]);

            return $payout->fresh();
        });
    }

    public function find(string $id): Payout
    {
        $payout = Payout::with(['lines', 'organizer'])->find($id);

        if (! $payout) {
            throw new RuntimeException('Payout not found.');
        }

        return $payout;
    }
}
