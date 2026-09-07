<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AdminCatalogReleaseResource extends CatalogReleaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'user_id' => $this->user_id,
            'assigned_admin_id' => $this->assigned_admin_id,
            'distribution' => $this->distribution,
            'allowed_transitions' => array_map(fn ($status) => $status->value, $this->status->next()),
            'audit' => $this->whenLoaded('activities', fn () => $this->activities->map(fn ($activity) => $activity->only([
                'id', 'actor_id', 'action', 'version', 'from_status', 'to_status', 'message', 'fields', 'internal', 'context', 'created_at',
            ]))),
        ]);
    }
}
