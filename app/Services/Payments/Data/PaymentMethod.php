<?php

namespace App\Services\Payments\Data;

use App\Enums\PaymentProvider;
use App\Enums\PaymentType;

/**
 * One entry from the config/payments.php catalog, resolved into typed form.
 */
readonly class PaymentMethod
{
    public function __construct(
        public string          $code,
        public string          $name,
        public string          $description,
        public PaymentProvider $provider,
        public PaymentType     $type,
        public ?string         $channelCode,
        public ?string         $logoUrl,
        public float           $feeFlat,
        public float           $feePercent,
        public ?int            $minAmount,
        public ?int            $maxAmount,
        public bool            $enabled,
        public array           $extra = [],
    ) {}

    public static function fromConfig(array $config): self
    {
        return new self(
            code:        $config['code'],
            name:        $config['name'],
            description: $config['description'] ?? '',
            provider:    PaymentProvider::from($config['provider']),
            type:        PaymentType::from($config['type']),
            channelCode: $config['channel_code'] ?? null,
            logoUrl:     $config['logo_url'] ?? null,
            feeFlat:     (float) ($config['fee_flat'] ?? 0),
            feePercent:  (float) ($config['fee_percent'] ?? 0),
            minAmount:   isset($config['min_amount']) ? (int) $config['min_amount'] : null,
            maxAmount:   isset($config['max_amount']) ? (int) $config['max_amount'] : null,
            enabled:     (bool) ($config['enabled'] ?? false),
            extra:       $config['account'] ?? [],
        );
    }

    /**
     * Gateway fee for a given order amount. Informational only — Plesticket
     * absorbs or passes this on as a business decision, it is not added to
     * the charge here.
     */
    public function feeFor(float $amount): float
    {
        return round($this->feeFlat + ($amount * $this->feePercent / 100), 2);
    }

    public function supportsAmount(float $amount): bool
    {
        if ($this->minAmount !== null && $amount < $this->minAmount) {
            return false;
        }

        return ! ($this->maxAmount !== null && $amount > $this->maxAmount);
    }
}
