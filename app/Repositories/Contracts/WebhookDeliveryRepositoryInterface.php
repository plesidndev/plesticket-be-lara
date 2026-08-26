<?php

namespace App\Repositories\Contracts;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;

interface WebhookDeliveryRepositoryInterface
{
    public function record(array $data): WebhookDelivery;

    /** Closes out a delivery with the outcome of dispatching it. */
    public function settle(
        WebhookDelivery $delivery,
        WebhookDeliveryStatus $status,
        ?string $error = null,
    ): WebhookDelivery;
}
