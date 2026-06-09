<?php

namespace App\Repositories;

use App\Models\Ticket;
use App\Repositories\Contracts\TicketRepositoryInterface;

class TicketRepository implements TicketRepositoryInterface
{
    public function findByCode(string $code): ?Ticket
    {
        return Ticket::with(['event', 'ticketType', 'buyer', 'orderItem', 'scannedBy'])
            ->where('ticket_code', $code)
            ->first();
    }

    public function create(array $data): Ticket
    {
        return Ticket::create($data);
    }

    public function update(Ticket $ticket, array $data): Ticket
    {
        $ticket->update($data);
        return $ticket->fresh(['event', 'ticketType', 'buyer', 'scannedBy']);
    }
}
