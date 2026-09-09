<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'actor_name' => $this->actor_name,
            'actor_role' => $this->actor_role,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'subject_label' => $this->subject_label,
            'changes' => $this->changes,
            'ip' => $this->ip,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
