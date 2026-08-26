<?php

namespace App\Repositories\Contracts;

use App\Models\Payment;

interface PaymentRepositoryInterface
{
    public function create(array $data): Payment;

    public function update(Payment $payment, array $data): Payment;

    public function findByReferenceId(string $referenceId): ?Payment;

    /** The most recent unpaid, unexpired payment on an order, if any. */
    public function findActiveForOrder(string $orderId): ?Payment;

    /** @return \Illuminate\Database\Eloquent\Collection<int, Payment> */
    public function pendingForOrder(string $orderId): \Illuminate\Database\Eloquent\Collection;
}
