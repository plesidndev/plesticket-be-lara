<?php

namespace App\Repositories\Contracts;

use App\Models\TicketType;

interface TicketTypeRepositoryInterface
{
    public function createForEvent(string $eventId, array $types): void;
    public function syncForEvent(string $eventId, array $types): void;
    public function findForEvent(string $eventId, int $ticketTypeId): ?TicketType;
    public function add(string $eventId, array $data): TicketType;
    public function update(TicketType $type, array $data): TicketType;
    public function delete(TicketType $type): void;
    public function isReferencedByOrders(TicketType $type): bool;
}
