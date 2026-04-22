<?php

namespace App\Enums;

enum BusinessType: string
{
    case Individual = 'individual';
    case Company    = 'company';
    case Community  = 'community';

    public function label(): string
    {
        return match($this) {
            self::Individual => 'Individual',
            self::Company    => 'Company',
            self::Community  => 'Community',
        };
    }
}
