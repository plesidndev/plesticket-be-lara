<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An organizer for the admin directory: the account plus how many events they run. Deliberately
 * narrower than UserResource — this is a roster, not an account editor.
 */
class OrganizerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uid' => $this->uid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
            'events_count' => (int) ($this->events_count ?? 0),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
