<?php

namespace App\Repositories;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use App\Repositories\Contracts\WebhookDeliveryRepositoryInterface;

class WebhookDeliveryRepository implements WebhookDeliveryRepositoryInterface
{
    /**
     * Every callback recorded against these payment references, oldest first. Redeliveries are
     * separate rows by design, so an order can legitimately show several.
     *
     * @param  list<string>  $referenceIds
     * @return \Illuminate\Support\Collection<int, WebhookDelivery>
     */
    public function forReferenceIds(array $referenceIds): \Illuminate\Support\Collection
    {
        if ($referenceIds === []) {
            return collect();
        }

        return WebhookDelivery::query()
            ->whereIn('reference_id', $referenceIds)
            ->orderBy('created_at')
            ->get();
    }

    public function record(array $data): WebhookDelivery
    {
        return WebhookDelivery::create($data);
    }

    public function settle(
        WebhookDelivery $delivery,
        WebhookDeliveryStatus $status,
        ?string $error = null,
    ): WebhookDelivery {
        $delivery->update([
            'status'       => $status,
            'error'        => $error,
            'processed_at' => now(),
        ]);

        return $delivery;
    }
}
