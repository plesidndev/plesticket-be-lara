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
 * Every instrument is created the same way — a payment request carrying a
 * `payment_method` block — and every instrument is retracted the same way, by
 * expiring its payment method. That uniformity is the reason for using this API
 * over the older per-instrument endpoints: it is what makes a QRIS charge
 * cancellable when a buyer switches payment method.
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
    ) {}

    public function createCharge(Payment $payment, PaymentMethod $method): ChargeResult
    {
        $body = $this->post('/v3/payment_requests', [
            'reference_id'   => $payment->reference_id,
            'type'           => 'PAY',
            'country'        => 'ID',
            'currency'       => 'IDR',
            // IDR has no minor unit and Xendit rejects decimal amounts.
            'amount'         => (int) round((float) $payment->amount),
            'payment_method' => $this->paymentMethodBlock($payment, $method),
        ], 'Failed to create payment.');

        return $this->toChargeResult($body);
    }

    /**
     * The instrument-specific half of the request body.
     */
    private function paymentMethodBlock(Payment $payment, PaymentMethod $method): array
    {
        $expiresAt = $payment->expires_at?->toIso8601ZuluString();

        return match ($method->type) {
            PaymentType::Qris => [
                'type'        => 'QR_CODE',
                'reusability' => 'ONE_TIME_USE',
                'qr_code'     => [
                    'channel_code' => $method->channelCode ?? 'QRIS',
                ],
            ],
            PaymentType::VirtualAccount => [
                'type'            => 'VIRTUAL_ACCOUNT',
                'reusability'     => 'ONE_TIME_USE',
                'virtual_account' => [
                    'channel_code'       => $method->channelCode,
                    'channel_properties' => [
                        'customer_name' => $payment->order?->buyer?->name
                            ?? $payment->order?->buyer_name
                            ?? 'Plesticket',
                        'expires_at'    => $expiresAt,
                    ],
                ],
            ],
            default => throw new PaymentGatewayException(
                "Xendit gateway does not handle payment type \"{$method->type->value}\"."
            ),
        };
    }

    /**
     * Flattens the payment request response into the shape the rest of the
     * application speaks. VERIFY: the nesting of qr_string / account number
     * under channel_properties is version-dependent, so both the nested and
     * flat positions are checked.
     */
    private function toChargeResult(array $body): ChargeResult
    {
        $method     = $body['payment_method'] ?? [];
        $qr         = $method['qr_code'] ?? [];
        $va         = $method['virtual_account'] ?? [];
        $qrProps    = $qr['channel_properties'] ?? [];
        $vaProps    = $va['channel_properties'] ?? [];

        $expiresAt = $qrProps['expires_at']
            ?? $vaProps['expires_at']
            ?? $body['expires_at']
            ?? null;

        return new ChargeResult(
            // Webhooks quote the payment request...
            providerReference:       $body['payment_request_id'] ?? $body['id'] ?? null,
            // ...but expiring a charge needs the payment method.
            providerMethodReference: $method['id'] ?? null,
            qrString:                $qrProps['qr_string'] ?? $qr['qr_string'] ?? null,
            accountNumber:           $vaProps['virtual_account_number']
                ?? $vaProps['account_number']
                ?? $va['account_number']
                ?? null,
            expiresAt:               $expiresAt ? Carbon::parse($expiresAt) : null,
            raw:                     $body,
        );
    }

    /**
     * Expires the payment method, which retracts the instrument itself — a QR
     * that can no longer be scanned, a VA that can no longer be transferred to.
     *
     * This is the capability the older QR Codes API lacked, and the reason
     * switching payment method can now genuinely close the old charge instead
     * of merely abandoning it.
     */
    public function voidCharge(Payment $payment): void
    {
        if (blank($payment->provider_method_reference)) {
            Log::info('No Xendit payment method recorded; cancelling locally only.', [
                'reference_id' => $payment->reference_id,
            ]);

            return;
        }

        $this->post(
            '/v3/payment_methods/'.$payment->provider_method_reference.'/expire',
            [],
            'Failed to expire payment method.',
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
