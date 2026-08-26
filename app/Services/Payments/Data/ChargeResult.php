<?php

namespace App\Services\Payments\Data;

use Carbon\CarbonInterface;

/**
 * What a gateway hands back after creating a charge. Every provider-specific
 * shape collapses into this before it touches the Payment model.
 */
readonly class ChargeResult
{
    public function __construct(
        public ?string         $providerReference = null,
        public ?string         $providerMethodReference = null,
        public ?string         $qrString = null,
        public ?string         $accountNumber = null,
        public ?string         $checkoutUrl = null,
        public ?CarbonInterface $expiresAt = null,
        public array           $raw = [],
    ) {}
}
