<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'order_number'   => $this->order_number,
            'status'         => $this->status->value,
            'total_price'    => (float) $this->total_price,
            'payment_method' => $this->payment_method,
            'paid_at'        => $this->paid_at?->toISOString(),
            'expires_at'     => $this->expires_at?->toISOString(),
            'created_at'     => $this->created_at->toISOString(),
            'event'          => $this->whenLoaded('event', fn() => [
                'event_id'   => $this->event->event_id,
                'title'      => $this->event->title,
                'slug'       => $this->event->slug,
                'banner_url' => $this->event->banner_url
                    ? asset('storage/' . $this->event->banner_url)
                    : null,
                'start_date' => $this->event->start_date?->toDateString(),
                'venue_name' => $this->event->venue_name,
                'city'       => $this->event->city,
            ]),
            'items'          => $this->whenLoaded('items', fn() =>
                $this->items->map(fn($item) => [
                    'id'               => $item->id,
                    'ticket_type_id'   => $item->ticket_type_id,
                    'ticket_type_name' => $item->ticket_type_name,
                    'unit_price'       => (float) $item->unit_price,
                    'quantity'         => $item->quantity,
                    'subtotal'         => (float) $item->subtotal,
                    'tickets'          => $item->relationLoaded('tickets')
                        ? $item->tickets->map(fn($t) => [
                            'ticket_code' => $t->ticket_code,
                            'holder_name' => $t->holder_name,
                            'status'      => $t->status->value,
                            'scanned_at'  => $t->scanned_at?->toISOString(),
                        ])
                        : [],
                ])
            ),
        ];
    }
}
