<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\WebhookDeliveryStatus;
use App\Models\Payment;
use App\Models\WebhookDelivery;
use App\Services\AuditLogger;
use App\Services\PaymentService;
use App\Services\Payments\PaymentGatewayManager;
use App\Traits\ApiResponse;
use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOperationsController extends Controller
{
    use ApiResponse;

    public function refunds(Request $request): JsonResponse
    {
        $paginator = Payment::query()
            ->with('order:id,order_number')
            ->where('requires_refund', true)
            ->latest('paid_at')
            ->paginate($this->limit($request));

        $data = $paginator->getCollection()->map(fn (Payment $payment): array => [
            'id' => $payment->id,
            'reference_id' => $payment->reference_id,
            'order_number' => $payment->order?->order_number,
            'provider' => $payment->provider->value,
            'status' => $payment->status->value,
            'amount' => $payment->amount,
            'paid_at' => $payment->paid_at?->toISOString(),
        ]);

        return $this->paginated('Refund queue retrieved.', $data, $paginator);
    }

    public function webhooks(Request $request): JsonResponse
    {
        $paginator = WebhookDelivery::query()
            ->whereIn('status', ['unmatched', 'failed'])
            ->latest()
            ->paginate($this->limit($request));

        $data = $paginator->getCollection()->map(fn (WebhookDelivery $delivery): array => [
            'id' => $delivery->id,
            'provider' => $delivery->provider->value,
            'event_type' => $delivery->event_type,
            'reference_id' => $delivery->reference_id,
            'status' => $delivery->status->value,
            'error' => $delivery->error,
            'created_at' => $delivery->created_at->toISOString(),
        ]);

        return $this->paginated('Webhook queue retrieved.', $data, $paginator);
    }

    /** The full delivery history, not just the queue needing attention. */
    public function webhookLog(Request $request): JsonResponse
    {
        $query = WebhookDelivery::query()->latest('created_at')->latest('id');

        foreach (['provider', 'status', 'event_type', 'reference_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->query($field));
            }
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        $paginator = $query->paginate($this->limit($request));

        $data = $paginator->getCollection()->map(fn (WebhookDelivery $delivery): array => [
            'id' => $delivery->id,
            'provider' => $delivery->provider->value,
            'event_type' => $delivery->event_type,
            'reference_id' => $delivery->reference_id,
            'status' => $delivery->status->value,
            'error' => $delivery->error,
            'payload' => $delivery->payload,
            'processed_at' => $delivery->processed_at?->toISOString(),
            'created_at' => $delivery->created_at->toISOString(),
        ]);

        return $this->paginated('Webhook deliveries retrieved.', $data, $paginator);
    }

    /**
     * Re-apply a stored callback.
     *
     * The original row is never touched: a replay records its own delivery, so the history shows
     * that the first attempt failed and a human re-ran it, rather than rewriting the past.
     */
    public function replayWebhook(string $id, PaymentGatewayManager $gateways, PaymentService $payments, AuditLogger $audit): JsonResponse
    {
        $original = WebhookDelivery::find($id);

        if (! $original) {
            return $this->error('Webhook delivery not found.', 404);
        }

        $event = $gateways->for($original->provider)->parseWebhook($original->payload ?? []);

        if (! $event) {
            return $this->error('This callback carries no event that can be applied.', 422, [
                'delivery' => ['This callback carries no event that can be applied.'],
            ]);
        }

        $replay = WebhookDelivery::create([
            'provider' => $original->provider,
            'event_type' => $original->event_type,
            'reference_id' => $original->reference_id,
            'status' => WebhookDeliveryStatus::Received,
            'payload' => $original->payload,
        ]);

        try {
            $outcome = $payments->applyWebhook($event);
        } catch (Throwable $exception) {
            $replay->update(['status' => WebhookDeliveryStatus::Failed, 'error' => $exception->getMessage(), 'processed_at' => now()]);
            $audit->record('webhook.replayed', 'webhook_delivery', $original->id, $original->reference_id, ['outcome' => 'failed']);

            return $this->error('Replay failed: '.$exception->getMessage(), 422, ['delivery' => [$exception->getMessage()]]);
        }

        $replay->update(['status' => $outcome, 'processed_at' => now()]);
        $audit->record('webhook.replayed', 'webhook_delivery', $original->id, $original->reference_id, ['outcome' => $outcome->value]);

        return $this->success('Callback replayed.', ['id' => $replay->id, 'status' => $outcome->value]);
    }

    private function limit(Request $request): int
    {
        return min(100, max(1, $request->integer('limit', 20)));
    }
}
