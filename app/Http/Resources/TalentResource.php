<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TalentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAdmin = auth('api')->check() && auth('api')->user()?->role->value === 'SUPER_ADMIN';

        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'slug'         => $this->slug,
            'type'         => $this->type,
            'category'     => $this->category,
            'genre'        => $this->genre,
            'photo'        => $this->photo
                ? (str_starts_with($this->photo, 'http') ? $this->photo : asset('storage/' . $this->photo))
                : null,
            'bio'          => $this->bio,
            'origin_city'  => $this->origin_city,
            'instagram'    => $this->instagram,
            'tiktok'       => $this->tiktok,
            'youtube'      => $this->youtube,
            'spotify'      => $this->spotify,
            'is_verified'  => $this->is_verified,
            'is_active'    => $this->is_active,
            'submitted_by' => $this->submitted_by,
            'verified_by'  => $this->verified_by,
            'created_at'   => $this->created_at,

            // Contact — only visible to admin or the submitter
            'contact' => ($isAdmin || auth('api')->id() === $this->submitted_by) ? [
                'name'  => $this->contact_name,
                'phone' => $this->contact_phone,
                'email' => $this->contact_email,
            ] : null,
        ];
    }
}
