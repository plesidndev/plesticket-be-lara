<?php

namespace App\Repositories\Contracts;

interface TicketTypeRepositoryInterface
{
    public function createForEvent(string $eventId, array $types): void;
    public function syncForEvent(string $eventId, array $types): void;
}
