<?php

namespace App\Services\Payments\Gateways;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Payment;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\Data\ChargeResult;
use App\Services\Payments\Data\PaymentMethod;
use App\Services\Payments\Data\WebhookEvent;
use App\Services\Payments\PaymentGatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Xendit via the Payment Requests API.
 *
 * Every instrument is created as a v3 Payment Request using a top-level
 * `channel_code`. The resulting payment request can also be cancelled when a
 * buyer switches methods.
 *
 * VERIFY BEFORE GOING LIVE: the endpoint paths and the response field paths
 * marked below are the parts most likely to differ across Xendit API versions.
 * They are deliberately read defensively, and are all confined to
 * paymentMethodBlock() and toChargeResult().
 */
class XenditGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly ?string $secretKey = null,
        private readonly ?string $callbackToken = null,
        private readonly string $baseUrl = 'https://api.xendit.co',
        private readonly int    $timeout = 30,
        private readonly string $apiVersion = '2024-11-11',
    ) {}

    public function createCharge(Payment $payment, PaymentMethod $method): ChargeResult
    {
        $body = $this->post('/v3/payment_requests', [
            'reference_id'   => $payment->reference_id,
            'type'           => 'PAY',
            'country'        => 'ID',
            'currency'       => 'IDR',
            // IDR has no minor unit and Xendit rejects decimal amounts.
            'request_amount' => (int) round((float) $payment->amount),
            ...$this->paymentChannelFields($payment, $method),
        ], 'Failed to create payment.');

        return $this->toChargeResult($body);
    }

    /**
     * The instrument-specific half of the request body.
     */
    private function paymentChannelFields(Payment $payment, PaymentMethod $method): array
    {
        $expiresAt = $payment->expires_at?->toIso8601ZuluString();

        return match ($method->type) {
            PaymentType::Qris => [
                'channel_code' => $method->channelCode ?? 'QRIS',
                'channel_properties' => array_filter([
                    'expires_at' => $expiresAt,
                ]),
            ],
            PaymentType::VirtualAccount => [
                'channel_code' => $method->channelCode,
                'channel_properties' => [
                    'display_name' => $payment->order?->buyer?->name
                        ?? $payment->order?->buyer_name
                        ?? 'Plesticket',
                    'expires_at' => $expiresAt,
                ],
            ],
            default => throw new PaymentGatewayException(
                "Xendit gateway does not handle payment type \"{$method->type->value}\"."
            ),
        };
    }

    /**
     * Flattens v3's standardised customer actions into the instruction shape
     * used by the rest of the application.
     */
    private function toChargeResult(array $body): ChargeResult
    {
        $actions = is_array($body['actions'] ?? null) ? $body['actions'] : [];
        $qrString = $this->actionValue($actions, 'QR_STRING');
        $accountNumber = $this->actionValue($actions, 'VIRTUAL_ACCOUNT_NUMBER')
            ?? $this->actionValue($actions, 'PAYMENT_CODE');
        $checkoutUrl = $this->actionValue($actions, 'WEB_URL')
            ?? $this->actionValue($actions, 'DEEPLINK_URL');

        $expiresAt = $body['channel_properties']['expires_at']
            ?? $body['expires_at']
            ?? null;

        return new ChargeResult(
            providerReference:       $body['payment_request_id'] ?? $body['id'] ?? null,
            providerMethodReference: null,
            qrString:                $qrString,
            accountNumber:           $accountNumber,
            checkoutUrl:             $checkoutUrl,
            expiresAt:               $expiresAt ? Carbon::parse($expiresAt) : null,
            raw:                     $body,
        );
    }

    private function actionValue(array $actions, string $descriptor): ?string
    {
        foreach ($actions as $action) {
            if (($action['descriptor'] ?? null) === $descriptor && is_string($action['value'] ?? null)) {
                return $action['value'];
            }
        }

        return null;
    }

    /**
     * Cancels the payment request so its QR or VA can no longer be paid.
     */
    public function voidCharge(Payment $payment): void
    {
        if (blank($payment->provider_reference)) {
            Log::info('No Xendit payment request recorded; cancelling locally only.', [
                'reference_id' => $payment->reference_id,
            ]);

            return;
        }

        $this->post(
            '/v3/payment_requests/'.$payment->provider_reference.'/cancel',
            [],
            'Failed to cancel payment request.',
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        if (blank($this->callbackToken)) {
            Log::error('Xendit callback token is not configured; rejecting webhook.');

            return false;
        }

        $token = $request->header('x-callback-token');

        return is_string($token) && hash_equals($this->callbackToken, $token);
    }

    /**
     * Handles the {"event": ..., "data": {...}} envelope the Payment Requests
     * API emits (payment.succeeded / payment.failed), and stays tolerant of the
     * flat legacy payloads so callbacks in flight during a cutover still land.
     */
    public function parseWebhook(array $payload): ?WebhookEvent
    {
        $data  = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $event = $payload['event'] ?? null;

        $referenceId = $data['reference_id'] ?? $data['external_id'] ?? null;

        if (! is_string($referenceId) || $referenceId === '') {
            Log::warning('Xendit webhook without a usable reference id.', ['event' => $event]);

            return null;
        }

        $status = $this->mapStatus($event, $data);

        if ($status === null) {
            return null;
        }

        $paidAt = $data['payment_detail']['paid_at']
            ?? $data['transaction_timestamp']
            ?? $data['created']
            ?? null;

        $amount = $data['amount'] ?? $data['paid_amount'] ?? null;

        return new WebhookEvent(
            referenceId:       $referenceId,
            status:            $status,
            providerReference: $data['payment_request_id'] ?? $data['qr_id'] ?? $data['id'] ?? null,
            amount:            $amount !== null ? (float) $amount : null,
            paidAt:            $paidAt ? Carbon::parse($paidAt) : null,
            raw:               $payload,
        );
    }

    /**
     * Driven by the status field rather than the event name, so both
     * `payment.succeeded` and the legacy `qr.payment` map identically.
     *
     * Returns null for events we deliberately ignore, letting the webhook
     * endpoint acknowledge them without touching the order.
     */
    private function mapStatus(?string $event, array $data): ?PaymentStatus
    {
        // Legacy virtual account callbacks carry no status field — their
        // arrival *is* the payment.
        if ($event === null && isset($data['payment_id'], $data['external_id'])) {
            return PaymentStatus::Paid;
        }

        $status = strtoupper((string) ($data['status'] ?? ''));

        return match (true) {
            in_array($status, ['SUCCEEDED', 'COMPLETED', 'PAID', 'SETTLED'], true) => PaymentStatus::Paid,
            in_array($status, ['EXPIRED', 'INACTIVE'], true)                       => PaymentStatus::Expired,
            in_array($status, ['FAILED', 'VOIDED', 'CANCELED', 'CANCELLED'], true) => PaymentStatus::Failed,
            default                                                                => null,
        };
    }

    /**
     * @throws PaymentGatewayException
     */
    private function post(string $path, array $body, string $failureMessage): array
    {
        if (blank($this->secretKey)) {
            throw new PaymentGatewayException('Xendit secret key is not configured.');
        }

        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                // Required on every call — Xendit answers
                // "API version in header is required" without it.
                ->withHeaders(['api-version' => $this->apiVersion])
                ->acceptJson()
                ->timeout($this->timeout)
                ->retry(2, 200, throw: false)
                ->post($this->baseUrl.$path, $body);
        } catch (ConnectionException $e) {
            Log::error('Xendit unreachable.', ['path' => $path, 'error' => $e->getMessage()]);

            throw new PaymentGatewayException(
                'Payment provider is unreachable. Please try again.',
                ['path' => $path],
            );
        }

        if ($response->failed()) {
            $error = $response->json() ?? [];

            Log::error('Xendit request failed.', [
                'path'   => $path,
                'status' => $response->status(),
                'body'   => $error,
            ]);

            // Xendit returns a human-readable `message` alongside an error_code.
            throw new PaymentGatewayException(
                $error['message'] ?? $failureMessage,
                ['status' => $response->status(), 'body' => $error],
            );
        }

        return $response->json() ?? [];
    }
}
