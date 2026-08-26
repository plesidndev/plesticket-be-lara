<?php

namespace App\Services\Payments\Data;

use App\Enums\PaymentStatus;
use Carbon\CarbonInterface;

/**
 * A provider callback normalised into terms PaymentService understands.
 */
readonly class WebhookEvent
{
    public function __construct(
        public string           $referenceId,
        public PaymentStatus    $status,
        public ?string          $providerReference = null,
        public ?float           $amount = null,
        public ?CarbonInterface $paidAt = null,
        public array            $raw = [],
    ) {}
}
