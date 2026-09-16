<?php

namespace App\Repositories;

use App\Models\TicketType;
use App\Repositories\Contracts\TicketTypeRepositoryInterface;

class TicketTypeRepository implements TicketTypeRepositoryInterface
{
    public function createForEvent(string $eventId, array $types): void
    {
        foreach ($types as $type) {
            $this->add($eventId, $type);
        }
    }

    public function syncForEvent(string $eventId, array $types): void
    {
        TicketType::where('event_id', $eventId)->delete();
        $this->createForEvent($eventId, $types);
    }

    public function findForEvent(string $eventId, int $ticketTypeId): ?TicketType
    {
        return TicketType::where('event_id', $eventId)->find($ticketTypeId);
    }

    public function add(string $eventId, array $data): TicketType
    {
        return TicketType::create([
            'event_id'   => $eventId,
            'name'       => $data['name'],
            'description'=> $data['description'] ?? null,
            'price'      => $data['price'],
            'quota'      => $data['quota'],
            'is_active'  => $data['is_active'] ?? true,
            'sale_start' => $data['sale_start'] ?? null,
            'sale_end'   => $data['sale_end'] ?? null,
        ]);
    }

    public function update(TicketType $type, array $data): TicketType
    {
        $type->update($data);

        return $type->fresh();
    }

    public function delete(TicketType $type): void
    {
        $type->delete();
    }

    public function isReferencedByOrders(TicketType $type): bool
    {
        return $type->orderItems()->exists() || $type->tickets()->exists();
    }
}
