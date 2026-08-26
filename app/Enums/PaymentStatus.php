<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending   = 'pending';
    case Paid      = 'paid';
    case Expired   = 'expired';
    case Failed    = 'failed';

    /** Superseded because the buyer switched to a different payment method. */
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Pending   => 'Waiting for payment',
            self::Paid      => 'Paid',
            self::Expired   => 'Expired',
            self::Failed    => 'Failed',
            self::Cancelled => 'Replaced by another payment method',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
