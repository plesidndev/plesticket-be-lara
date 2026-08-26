<?php

namespace App\Repositories;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function create(array $data): Payment
    {
        return Payment::create($data);
    }

    public function update(Payment $payment, array $data): Payment
    {
        $payment->update($data);

        return $payment->fresh();
    }

    public function findByReferenceId(string $referenceId): ?Payment
    {
        return Payment::with('order')->where('reference_id', $referenceId)->first();
    }

    public function findActiveForOrder(string $orderId): ?Payment
    {
        return Payment::where('order_id', $orderId)
            ->where('status', PaymentStatus::Pending)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('created_at')
            ->first();
    }

    public function pendingForOrder(string $orderId): Collection
    {
        return Payment::where('order_id', $orderId)
            ->where('status', PaymentStatus::Pending)
            ->get();
    }
}
