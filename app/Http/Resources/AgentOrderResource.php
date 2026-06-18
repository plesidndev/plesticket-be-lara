<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $commissionRate = (float) ($this->agent?->commission_rate ?? 0);

        return [
            'order_number'     => $this->order_number,
            'status'           => $this->status->value,
            'buyer_name'       => $this->buyer_name,
            'buyer_phone'      => $this->buyer_phone,
            'total_price'      => (float) $this->total_price,
            'commission_earned'=> round((float) $this->total_price * $commissionRate / 100, 2),
            'payment_method'   => $this->payment_method,
            'paid_at'          => $this->paid_at?->toISOString(),
            'created_at'       => $this->created_at->toISOString(),
            'event'            => $this->whenLoaded('event', fn() => [
                'event_id'   => $this->event->event_id,
                'title'      => $this->event->title,
                'start_date' => $this->event->start_date?->toDateString(),
                'venue_name' => $this->event->venue_name,
                'city'       => $this->event->city,
            ]),
            'items'            => $this->whenLoaded('items', fn() =>
                $this->items->map(fn($item) => [
                    'ticket_type_name' => $item->ticket_type_name,
                    'unit_price'       => (float) $item->unit_price,
                    'quantity'         => $item->quantity,
                    'subtotal'         => (float) $item->subtotal,
                    'tickets'          => $item->relationLoaded('tickets')
                        ? $item->tickets->map(fn($t) => [
                            'ticket_code' => $t->ticket_code,
                            'holder_name' => $t->holder_name,
                            'status'      => $t->status->value,
                        ])
                        : [],
                ])
            ),
        ];
    }
}
