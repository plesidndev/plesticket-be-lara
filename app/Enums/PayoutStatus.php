<?php

namespace App\Enums;

enum PayoutStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    /** Once paid, the lines are settled and the payout is immutable. */
    public function isFinal(): bool
    {
        return $this === self::Paid || $this === self::Cancelled;
    }
}
