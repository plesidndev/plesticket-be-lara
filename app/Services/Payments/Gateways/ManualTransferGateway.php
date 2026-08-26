<?php

namespace App\Services\Payments\Gateways;

use App\Models\Payment;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\Data\ChargeResult;
use App\Services\Payments\Data\PaymentMethod;
use App\Services\Payments\Data\WebhookEvent;
use Illuminate\Http\Request;

/**
 * Bank transfer with no provider behind it: the buyer transfers to a company
 * account and a human confirms receipt. Creating the "charge" only means
 * handing back the account details to display.
 *
 * There is no callback — confirmation arrives through an admin action, so
 * verifyWebhook() always denies.
 */
class ManualTransferGateway implements PaymentGatewayInterface
{
    public function createCharge(Payment $payment, PaymentMethod $method): ChargeResult
    {
        return new ChargeResult(
            accountNumber: $method->extra['account_number'] ?? null,
            expiresAt:     $payment->expires_at,
            raw:           $method->extra,
        );
    }

    public function voidCharge(Payment $payment): void
    {
        // Nothing exists on any provider to retract — the instruction was only
        // ever a set of account details shown to the buyer.
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }

    public function parseWebhook(array $payload): ?WebhookEvent
    {
        return null;
    }
}
