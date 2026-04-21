<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin     = 'SUPER_ADMIN';
    case EventOrganizer = 'EVENT_ORGANIZER';
    case RegisteredUser = 'REGISTERED_USER';

    public function label(): string
    {
        return match($this) {
            self::SuperAdmin     => 'Super Admin',
            self::EventOrganizer => 'Event Organizer',
            self::RegisteredUser => 'Registered User',
        };
    }
}