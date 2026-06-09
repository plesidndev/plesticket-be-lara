<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ticket_code'  => $this->ticket_code,
            'holder_name'  => $this->holder_name,
            'status'       => $this->status->value,
            'scanned_at'   => $this->scanned_at?->toISOString(),
            'scanned_by'   => $this->scannedBy ? [
                'uid'  => $this->scannedBy->uid,
                'name' => $this->scannedBy->name,
            ] : null,
            'event'        => $this->whenLoaded('event', fn() => [
                'event_id' => $this->event->event_id,
                'title'    => $this->event->title,
                'slug'     => $this->event->slug,
            ]),
            'ticket_type'  => $this->whenLoaded('ticketType', fn() => [
                'id'   => $this->ticketType->id,
                'name' => $this->ticketType->name,
            ]),
            'order_number' => $this->whenLoaded('order', fn() => $this->order->order_number),
        ];
    }
}
