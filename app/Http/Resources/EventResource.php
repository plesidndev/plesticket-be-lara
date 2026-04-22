<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\TicketTypeResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAdmin = auth('api')->check() && auth('api')->user()?->role->value === 'SUPER_ADMIN';
        $isOwner = auth('api')->check() && auth('api')->id() === $this->user_id;

        return [
            'id'           => $this->id,
            'event_id'     => $this->event_id,
            'title'        => $this->title,
            'slug'         => $this->slug,
            'description'  => $this->description,
            'category'     => $this->category,
            'banner_url'   => $this->banner_url,

            // PIC — only visible to admin or owner
            'pic' => ($isAdmin || $isOwner) ? [
                'name'          => $this->pic_name,
                'identity_type' => $this->pic_identity_type->value,
                'identity_type_label' => $this->pic_identity_type->label(),
                'identity_number' => $isAdmin ? $this->pic_identity_number : null,
                'npwp'          => $isAdmin ? $this->pic_npwp : null,
            ] : null,

            'schedule' => [
                'start_date' => $this->start_date?->format('Y-m-d'),
                'end_date'   => $this->end_date?->format('Y-m-d'),
                'start_time' => $this->start_time,
                'end_time'   => $this->end_time,
            ],

            'location' => [
                'is_online'  => $this->is_online,
                'venue_name' => $this->venue_name,
                'address'    => $this->address,
                'city'       => $this->city,
                'province'   => $this->province,
                'latitude'   => $this->latitude,
                'longitude'  => $this->longitude,
            ],

            'verification_status' => $this->verification_status->value,
            'verification_label'  => $this->verification_status->label(),
            'rejection_reason'    => $this->rejection_reason,
            'verified_at'         => $this->verified_at?->toISOString(),
            'show_status'         => $this->show_status,
            'is_published'        => $this->is_published,
            'created_at'          => $this->created_at,

            'ticket_types' => TicketTypeResource::collection($this->whenLoaded('ticketTypes')),

            'organizer'   => new UserResource($this->whenLoaded('user')),
            'verified_by' => $isAdmin ? new UserResource($this->whenLoaded('verifiedBy')) : null,
        ];
    }
}
