<?php

namespace App\Console\Commands;

use App\Models\WebhookDelivery;
use Illuminate\Console\Command;

/**
 * Trims the webhook delivery audit trail.
 *
 * Every provider retry writes another row carrying a full JSON payload, and nothing else removes
 * them, so a single stuck payment can grow the table indefinitely. Only settled deliveries are
 * pruned: `failed` and `unmatched` are the queue a human still has to work through, so they are
 * kept regardless of age.
 */
class PruneWebhookDeliveries extends Command
{
    protected $signature = 'webhooks:prune
                            {--days=90 : Age in days beyond which a settled delivery is removed}
                            {--chunk=1000 : Rows to delete per statement}';

    protected $description = 'Remove settled webhook deliveries older than the retention window';

    /** Statuses that represent a finished delivery needing no further attention. */
    private const SETTLED = ['applied', 'ignored', 'skipped'];

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $chunk = max(1, (int) $this->option('chunk'));
        $cutoff = now()->subDays($days);

        $removed = 0;

        do {
            // Ids first: DELETE ... LIMIT is MySQL-only, and this has to run on SQLite too.
            $ids = WebhookDelivery::query()
                ->whereIn('status', self::SETTLED)
                ->where('created_at', '<', $cutoff)
                ->limit($chunk)
                ->pluck('id');

            $deleted = $ids->isEmpty() ? 0 : WebhookDelivery::whereIn('id', $ids)->delete();
            $removed += $deleted;
        } while ($deleted > 0);

        $this->info("Pruned {$removed} settled webhook deliveries older than {$days} days.");

        return self::SUCCESS;
    }
}
