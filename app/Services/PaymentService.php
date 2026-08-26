<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\WebhookDeliveryStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\Payments\Data\PaymentMethod;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentMethodCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class PaymentService
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly PaymentGatewayManager      $gateways,
        private readonly PaymentMethodCatalog       $catalog,
        private readonly OrderService               $orders,
    ) {}

    /** @return Collection<int, array> */
    public function availableMethods(): Collection
    {
        return $this->catalog->groupedByType();
    }

    /**
     * Creates a charge for an order and returns the payment instruction.
     *
     * Re-requesting a payment while one is still live returns the existing
     * record rather than charging twice — a buyer refreshing checkout must not
     * end up with two QR codes for one order.
     */
    public function createForOrder(string $orderNumber, int $buyerId, string $methodCode): Payment
    {
        $order = $this->orders->findByOrderNumber($orderNumber, $buyerId);

        // Throws if already paid/cancelled, and expires a lapsed order.
        $this->orders->assertPayable($order);

        $method = $this->resolveMethod($methodCode, (float) $order->total_price);

        $existing = $this->payments->findActiveForOrder($order->id);

        if ($existing) {
            // Same method: hand back the live instruction rather than charging
            // twice — a buyer refreshing checkout must not get a second QR.
            if ($existing->method_code === $method->code) {
                return $existing;
            }

            // Different method: the buyer switched. Retire the old charge first
            // so an order never has two payable instruments open at once.
            $this->supersede($existing);
        }

        return $this->charge($order, $method);
    }

    /**
     * Retires a charge the buyer has moved away from.
     *
     * Cancelling locally is the part that must not fail, so a provider that
     * cannot retract its charge (Xendit dynamic QR codes, for one) never blocks
     * the switch. The remaining exposure — the buyer paying the abandoned QR
     * anyway — is caught in settle(), which honours the money rather than
     * dropping it.
     */
    private function supersede(Payment $payment): void
    {
        try {
            $this->gateways->for($payment->provider)->voidCharge($payment);
        } catch (\Throwable $e) {
            Log::warning('Could not retract a superseded charge at the provider.', [
                'reference_id' => $payment->reference_id,
                'method_code'  => $payment->method_code,
                'error'        => $e->getMessage(),
            ]);
        }

        $this->payments->update($payment, ['status' => PaymentStatus::Cancelled]);
    }

    private function resolveMethod(string $code, float $amount): PaymentMethod
    {
        $method = $this->catalog->find($code);

        if (! $method) {
            throw new RuntimeException("Payment method \"{$code}\" not found.");
        }

        if (! $method->enabled) {
            throw new InvalidArgumentException("Payment method \"{$method->name}\" is not available yet.");
        }

        if (! $method->supportsAmount($amount)) {
            throw new InvalidArgumentException(
                "{$method->name} does not accept this order amount."
            );
        }

        return $method;
    }

    private function charge(Order $order, PaymentMethod $method): Payment
    {
        // The payment can never outlive the order's quota hold.
        $expiresAt = collect([
            now()->addMinutes((int) config('payments.expiry_minutes', 30)),
            $order->expires_at,
        ])->filter()->min();

        $payment = $this->payments->create([
            'order_id'     => $order->id,
            'reference_id' => $this->generateReferenceId($order),
            'provider'     => $method->provider,
            'method_code'  => $method->code,
            'type'         => $method->type,
            'status'       => PaymentStatus::Pending,
            'amount'       => $order->total_price,
            'expires_at'   => $expiresAt,
        ]);

        $payment->setRelation('order', $order);

        $result = $this->gateways->for($method->provider)->createCharge($payment, $method);

        return $this->payments->update($payment, [
            'provider_reference'        => $result->providerReference,
            'provider_method_reference' => $result->providerMethodReference,
            'qr_string'                 => $result->qrString,
            'account_number'            => $result->accountNumber,
            'checkout_url'              => $result->checkoutUrl,
            'expires_at'                => $result->expiresAt ?? $payment->expires_at,
            'provider_payload'          => $result->raw,
        ]);
    }

    public function findForBuyer(string $orderNumber, int $buyerId): Payment
    {
        $order   = $this->orders->findByOrderNumber($orderNumber, $buyerId);
        $payment = $this->payments->findActiveForOrder($order->id)
            ?? $order->payments()->latest()->first();

        if (! $payment) {
            throw new RuntimeException('No payment has been started for this order.');
        }

        return $payment;
    }

    /**
     * Applies a normalised gateway callback.
     *
     * Every branch returns rather than throws: a provider retries anything it
     * does not get a 2xx for, and re-delivering a callback we have already
     * handled (or cannot match) achieves nothing.
     *
     * The returned status is what the caller records against the delivery.
     */
    public function applyWebhook(\App\Services\Payments\Data\WebhookEvent $event): WebhookDeliveryStatus
    {
        $payment = $this->payments->findByReferenceId($event->referenceId);

        if (! $payment) {
            Log::warning('Payment webhook for unknown reference.', [
                'reference_id' => $event->referenceId,
            ]);

            return WebhookDeliveryStatus::Unmatched;
        }

        // A confirmation is acted on whatever state we hold locally: if the
        // buyer paid a charge we had already superseded, the money is real and
        // must not be dropped on the floor.
        if ($event->status === PaymentStatus::Paid) {
            return $this->settle($payment, $event);
        }

        // Every other outcome only means something while the charge is still
        // awaiting payment.
        if (! $payment->isPending()) {
            Log::info('Payment webhook for a charge that is no longer pending; ignoring.', [
                'reference_id' => $event->referenceId,
                'status'       => $payment->status->value,
            ]);

            return WebhookDeliveryStatus::Skipped;
        }

        $this->payments->update($payment, [
            'status'           => $event->status,
            'provider_payload' => $event->raw,
        ]);

        // A charge can lapse while its order still has time on the clock — the
        // buyer may simply pick another method. Only release the seats once the
        // order's own hold has run out.
        if ($payment->order && $payment->order->isExpired()) {
            $this->orders->expire($payment->order);
        }

        return WebhookDeliveryStatus::Applied;
    }

    private function settle(Payment $payment, \App\Services\Payments\Data\WebhookEvent $event): WebhookDeliveryStatus
    {
        if ($payment->status === PaymentStatus::Paid) {
            Log::info('Redelivered confirmation for an already-paid charge; ignoring.', [
                'reference_id' => $payment->reference_id,
            ]);

            return WebhookDeliveryStatus::Skipped;
        }

        // Guard against a callback that reports less than we asked for. Tickets
        // are issued only for the full amount; anything else needs a human.
        if ($event->amount !== null && $event->amount + 0.01 < (float) $payment->amount) {
            Log::error('Payment webhook reported an underpayment.', [
                'reference_id' => $payment->reference_id,
                'expected'     => (float) $payment->amount,
                'received'     => $event->amount,
            ]);

            $this->payments->update($payment, [
                'status'           => PaymentStatus::Failed,
                'provider_payload' => $event->raw,
            ]);

            return WebhookDeliveryStatus::Applied;
        }

        if ($payment->status === PaymentStatus::Cancelled) {
            Log::warning('A superseded charge was paid anyway.', [
                'reference_id' => $payment->reference_id,
                'method_code'  => $payment->method_code,
            ]);
        }

        $order  = $payment->order;
        $paidAt = $event->paidAt ?? now();

        // The order is already settled — by the method the buyer switched to,
        // or by the direct pay route. This is a second, genuine payment.
        $duplicate = $order && $order->status === OrderStatus::Paid;

        $this->payments->update($payment, [
            'status'             => PaymentStatus::Paid,
            'paid_at'            => $paidAt,
            'requires_refund'    => $duplicate,
            'provider_reference' => $event->providerReference ?? $payment->provider_reference,
            'provider_payload'   => $event->raw,
        ]);

        if ($duplicate) {
            Log::critical('Order paid twice — refund required.', [
                'order_number' => $order->order_number,
                'reference_id' => $payment->reference_id,
                'amount'       => (float) $payment->amount,
                'method_code'  => $payment->method_code,
            ]);

            return WebhookDeliveryStatus::Applied;
        }

        if (! $order) {
            Log::error('Paid payment has no order attached.', [
                'reference_id' => $payment->reference_id,
            ]);

            return WebhookDeliveryStatus::Unmatched;
        }

        // Issues tickets; idempotent, so a duplicate callback is harmless.
        $this->orders->markPaid($order, $payment->method_code, $paidAt);

        return WebhookDeliveryStatus::Applied;
    }

    private function generateReferenceId(Order $order): string
    {
        do {
            $reference = $order->order_number.'-'.strtoupper(Str::random(6));
        } while (Payment::where('reference_id', $reference)->exists());

        return $reference;
    }
}
