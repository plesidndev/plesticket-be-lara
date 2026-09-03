<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\WebhookDelivery;
use App\Traits\ApiResponse;
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

    private function limit(Request $request): int
    {
        return min(100, max(1, $request->integer('limit', 20)));
    }
}
