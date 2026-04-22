<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'price'       => $this->price,
            'quota'       => $this->quota,
            'is_active'   => $this->is_active,
            'sale_start'  => $this->sale_start?->toISOString(),
            'sale_end'    => $this->sale_end?->toISOString(),
        ];
    }
}
