<?php

namespace App\Enums;

enum IdentityType: string
{
    case Ktp      = 'ktp';
    case Sim      = 'sim';
    case Passport = 'passport';

    public function label(): string
    {
        return match($this) {
            self::Ktp      => 'KTP',
            self::Sim      => 'SIM',
            self::Passport => 'Passport',
        };
    }
}
