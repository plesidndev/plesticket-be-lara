<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case Pending   = 'pending';
    case Verified  = 'verified';
    case Rejected  = 'rejected';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match($this) {
            self::Pending   => 'Pending',
            self::Verified  => 'Verified',
            self::Rejected  => 'Rejected',
            self::Suspended => 'Suspended',
        };
    }
}
