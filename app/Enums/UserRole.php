<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin     = 'SUPER_ADMIN';
    case Admin          = 'ADMIN';
    case RegisteredUser = 'REGISTERED_USER';

    public function label(): string
    {
        return match($this) {
            self::SuperAdmin     => 'Super Admin',
            self::Admin          => 'Admin',
            self::RegisteredUser => 'Registered User',
        };
    }

    /**
     * Platform staff: the console roles. ADMIN sees everything SUPER_ADMIN does except
     * /api/users, so it cannot promote itself or mint further staff accounts.
     */
    public function isStaff(): bool
    {
        return $this === self::SuperAdmin || $this === self::Admin;
    }
}
