<?php

namespace App\Console\Commands;

use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Releases ticket quota held by orders that were never paid.
 *
 * Order expiry is otherwise lazy — it only fires when something touches the
 * order, and an abandoned order is by definition never touched. Without this
 * sweeper a buyer who closes the tab holds those seats forever, and the event
 * reads as sold out while the quota sits with someone who left.
 */
class ExpireAbandonedOrders extends Command
{
    protected $signature = 'orders:expire
                            {--limit=500 : Maximum orders to sweep in one run}';

    protected $description = 'Release ticket quota held by unpaid, lapsed orders';

    public function handle(
        OrderRepositoryInterface $orders,
        OrderService $orderService,
        PaymentService $payments,
    ): int {
        $limit    = max(1, (int) $this->option('limit'));
        $expired  = $orders->pendingExpired($limit);
        $released = 0;
        $failed   = 0;

        foreach ($expired as $order) {
            try {
                // False means someone else got there first — a buyer hitting
                // the pay route at the same moment. Not an error.
                if (! $orderService->expire($order)) {
                    continue;
                }

                $payments->expirePendingFor($order);
                $released++;
            } catch (Throwable $e) {
                $failed++;

                // One bad order must not strand the rest of the batch.
                Log::error('Failed to expire an abandoned order.', [
                    'order_number' => $order->order_number,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        $this->info("Swept {$expired->count()} lapsed order(s): {$released} released, {$failed} failed.");

        if ($released > 0) {
            Log::info('Released quota from abandoned orders.', ['count' => $released]);
        }

        // A partial failure is still worth a non-zero exit so a scheduler or
        // monitor can notice.
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
