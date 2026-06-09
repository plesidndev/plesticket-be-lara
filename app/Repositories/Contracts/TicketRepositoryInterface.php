<?php

namespace App\Repositories\Contracts;

use App\Models\Ticket;

interface TicketRepositoryInterface
{
    public function findByCode(string $code): ?Ticket;
    public function create(array $data): Ticket;
    public function update(Ticket $ticket, array $data): Ticket;
}
