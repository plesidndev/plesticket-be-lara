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
     * Platform staff: the console roles. Authorization itself runs on per-account permission
     * grants, not on this — a role only decides which preset a new account starts from.
     */
    public function isStaff(): bool
    {
        return $this === self::SuperAdmin || $this === self::Admin;
    }

    /**
     * The grants a newly created account of this role starts with.
     *
     * A new admin gets the console door and the overview and nothing else — every screen is
     * granted deliberately afterwards, so nobody is handed moderation or catalog access by the
     * act of being created. A preset, not a rule: once an account exists its own rows in
     * `user_permissions` are the only thing consulted, so two admins can legitimately differ.
     *
     * SuperAdmin gets an empty list because it bypasses the check entirely — writing rows for it
     * would imply they could be revoked.
     *
     * @return list<Permission>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SuperAdmin => [],
            self::Admin => [
                Permission::ConsoleAccess,
                Permission::SummaryView,
            ],
            self::RegisteredUser => [],
        };
    }
}
