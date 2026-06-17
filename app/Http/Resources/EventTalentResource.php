<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventTalentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'talent_id'        => $this->talent_id,
            'talent'           => $this->talent_id ? new TalentResource($this->whenLoaded('talent')) : null,
            'free_name'        => $this->free_name,
            'display_name'     => $this->display_name,
            'role'             => $this->role,
            'performance_order' => $this->performance_order,
            'performance_time' => $this->performance_time,
        ];
    }
}
