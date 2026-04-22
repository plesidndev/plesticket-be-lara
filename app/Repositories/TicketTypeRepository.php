<?php

namespace App\Repositories;

use App\Models\TicketType;
use App\Repositories\Contracts\TicketTypeRepositoryInterface;

class TicketTypeRepository implements TicketTypeRepositoryInterface
{
    public function createForEvent(string $eventId, array $types): void
    {
        foreach ($types as $type) {
            TicketType::create([
                'event_id'   => $eventId,
                'name'       => $type['name'],
                'description'=> $type['description'] ?? null,
                'price'      => $type['price'],
                'quota'      => $type['quota'],
                'is_active'  => $type['is_active'] ?? true,
                'sale_start' => $type['sale_start'] ?? null,
                'sale_end'   => $type['sale_end'] ?? null,
            ]);
        }
    }

    public function syncForEvent(string $eventId, array $types): void
    {
        TicketType::where('event_id', $eventId)->delete();
        $this->createForEvent($eventId, $types);
    }
}
