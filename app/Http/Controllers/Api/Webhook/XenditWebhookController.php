<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Enums\PaymentProvider;
use App\Enums\WebhookDeliveryStatus;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\WebhookDeliveryRepositoryInterface;
use App\Services\PaymentService;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Receives Xendit callbacks. Unauthenticated by design — the request is
 * verified with the callback token Xendit signs it with, not a user session.
 *
 * Every verified callback is written to webhook_deliveries before it is
 * dispatched, so a payload that fails mid-flight — or that matches no payment
 * at all — survives as something a human can inspect and replay.
 */
class XenditWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayManager              $gateways,
        private readonly PaymentService                     $payments,
        private readonly WebhookDeliveryRepositoryInterface $deliveries,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $gateway = $this->gateways->for(PaymentProvider::Xendit);

        // Rejected callbacks are logged but never stored: this endpoint is
        // public, so persisting unverified payloads would let anyone fill the
        // table at will.
        if (! $gateway->verifyWebhook($request)) {
            Log::warning('Rejected Xendit webhook with an invalid callback token.', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Invalid callback token.'], 401);
        }

        $payload = $request->all();
        $event   = $gateway->parseWebhook($payload);

        $delivery = $this->deliveries->record([
            'provider'     => PaymentProvider::Xendit,
            'event_type'   => is_string($payload['event'] ?? null) ? $payload['event'] : null,
            'reference_id' => $event?->referenceId,
            'status'       => WebhookDeliveryStatus::Received,
            'payload'      => $payload,
        ]);

        if (! $event) {
            // An event we do not act on. Acknowledge so Xendit stops retrying.
            $this->deliveries->settle($delivery, WebhookDeliveryStatus::Ignored);

            return response()->json(['status' => 'ignored']);
        }

        try {
            $outcome = $this->payments->applyWebhook($event);
        } catch (Throwable $e) {
            Log::error('Failed to apply Xendit webhook.', [
                'reference_id' => $event->referenceId,
                'error'        => $e->getMessage(),
            ]);

            $this->deliveries->settle($delivery, WebhookDeliveryStatus::Failed, $e->getMessage());

            // Return non-2xx so Xendit retries; the charge is real money and
            // must not be dropped because of a transient failure on our side.
            return response()->json(['status' => 'error', 'message' => 'Processing failed.'], 500);
        }

        $this->deliveries->settle($delivery, $outcome);

        return response()->json(['status' => 'ok']);
    }
}
