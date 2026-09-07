<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogReleaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'type' => $this->type, 'title' => $this->title,
            'metadata' => $this->metadata, 'status' => $this->status->value,
            'editable' => $this->status->editable(), 'version' => $this->version,
            'has_change_request' => $this->has_change_request,
            'identifiers' => array_intersect_key($this->distribution ?? [], array_flip(['upc', 'isrcs', 'video_isrc'])),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'assets' => $this->whenLoaded('assets', fn () => $this->assets->map(fn ($asset) => $asset->only(['id', 'kind', 'track_id', 'original_name', 'mime_type', 'size', 'sha256']))),
            'submission_versions' => $this->whenLoaded('submissions', fn () => $this->submissions->map(fn ($submission) => [
                'version' => $submission->version, 'created_at' => $submission->created_at->toIso8601String(),
            ])),
            'history' => $this->whenLoaded('activities', fn () => $this->activities->reject(fn ($activity) => $activity->internal)->values()->map(fn ($activity) => [
                'id' => $activity->id, 'action' => $activity->action, 'version' => $activity->version,
                'from_status' => $activity->from_status, 'to_status' => $activity->to_status,
                'message' => $activity->message, 'fields' => $activity->fields,
                'created_at' => $activity->created_at->toIso8601String(),
            ])),
            'stores' => $this->whenLoaded('deliveries', fn () => $this->deliveries->map(fn ($delivery) => [
                'store' => $delivery->store, 'status' => $delivery->status, 'url' => $delivery->url,
                'version' => $delivery->version, 'live_at' => $delivery->live_at?->toIso8601String(),
            ])),
        ];
    }
}
