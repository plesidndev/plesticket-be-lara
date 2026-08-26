<?php

namespace App\Services\Payments\Contracts;

use App\Models\Payment;
use App\Services\Payments\Data\ChargeResult;
use App\Services\Payments\Data\PaymentMethod;
use App\Services\Payments\Data\WebhookEvent;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /**
     * Create the charge on the provider and return the buyer-facing
     * instruction (QR string, VA number, checkout URL...).
     *
     * @throws \App\Services\Payments\PaymentGatewayException
     */
    public function createCharge(Payment $payment, PaymentMethod $method): ChargeResult;

    /**
     * Retract a charge so it can no longer be paid — used when the buyer
     * switches to a different payment method.
     *
     * Best effort by contract: not every provider can retract every charge
     * type. Implementations return normally when there is nothing to do, and
     * callers must not assume the instrument is dead on the provider's side.
     *
     * @throws \App\Services\Payments\PaymentGatewayException
     */
    public function voidCharge(Payment $payment): void;

    /**
     * Confirm the callback genuinely came from this provider. Called before
     * the payload is parsed or trusted in any way.
     */
    public function verifyWebhook(Request $request): bool;

    /**
     * Normalise a callback body. Returns null when the event is one we do not
     * act on (provider heartbeats, statuses we ignore).
     */
    public function parseWebhook(array $payload): ?WebhookEvent;
}
