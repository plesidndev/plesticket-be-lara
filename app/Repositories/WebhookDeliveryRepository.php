<?php

namespace App\Repositories;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use App\Repositories\Contracts\WebhookDeliveryRepositoryInterface;

class WebhookDeliveryRepository implements WebhookDeliveryRepositoryInterface
{
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
