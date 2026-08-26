<?php

namespace App\Enums;

/**
 * The gateway that actually processes a payment.
 *
 * Each provider has a matching implementation of PaymentGatewayInterface,
 * wired in config/payments.php under `providers`.
 */
enum PaymentProvider: string
{
    case Xendit = 'xendit';
    case Manual = 'manual';

    public function label(): string
    {
        return match($this) {
            self::Xendit => 'Xendit',
            self::Manual => 'Manual Verification',
        };
    }
}
