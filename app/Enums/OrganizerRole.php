<?php

namespace App\Enums;

enum OrganizerRole: string
{
    case EoStaff        = 'EO_STAFF';
    case GateOfficer    = 'GATE_OFFICER';
    case MitraTicketBox = 'MITRA_TICKET_BOX';
    case Band           = 'BAND';
    case Media          = 'MEDIA';
    case Sponsor        = 'SPONSOR';

    public function label(): string
    {
        return match($this) {
            self::EoStaff        => 'EO Staff',
            self::GateOfficer    => 'Gate Officer',
            self::MitraTicketBox => 'Mitra Ticket Box',
            self::Band           => 'Band / Musisi',
            self::Media          => 'Media',
            self::Sponsor        => 'Sponsor',
        };
    }
}
