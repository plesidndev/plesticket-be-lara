<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventTalentResource;
use App\Models\EventTalent;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventTalentController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly EventRepositoryInterface $events) {}

    public function index(string $eventId): JsonResponse
    {
        $event = $this->events->findById($eventId);
        if (! $event) return $this->error('Event not found.', 404);

        $talents = EventTalent::with('talent')
            ->where('event_id', $event->id)
            ->orderBy('performance_order')
            ->get();

        return $this->success('Lineup retrieved.', EventTalentResource::collection($talents));
    }

    public function store(Request $request, string $eventId): JsonResponse
    {
        $event = $this->events->findById($eventId);
        if (! $event) return $this->error('Event not found.', 404);

        if ($event->user_id !== auth('api')->id()) {
            return $this->error('Forbidden.', 403);
        }

        $data = $request->validate([
            'talent_id'         => ['nullable', 'integer', 'exists:talents,id'],
            'free_name'         => ['nullable', 'string', 'max:150'],
            'role'              => ['required', 'in:headliner,opening_act,dj,mc,special_guest,performer'],
            'performance_order' => ['nullable', 'integer', 'min:0'],
            'performance_time'  => ['nullable', 'string', 'max:50'],
        ]);

        if (empty($data['talent_id']) && empty($data['free_name'])) {
            return $this->error('Either talent_id or free_name is required.', 422);
        }

        $talent = EventTalent::create([
            'event_id'          => $event->id,
            'talent_id'         => $data['talent_id'] ?? null,
            'free_name'         => $data['free_name'] ?? null,
            'role'              => $data['role'],
            'performance_order' => $data['performance_order'] ?? 0,
            'performance_time'  => $data['performance_time'] ?? null,
        ]);

        $talent->load('talent');

        return $this->created('Talent added to lineup.', new EventTalentResource($talent));
    }

    public function update(Request $request, string $eventId, int $id): JsonResponse
    {
        $event = $this->events->findById($eventId);
        if (! $event) return $this->error('Event not found.', 404);

        if ($event->user_id !== auth('api')->id()) {
            return $this->error('Forbidden.', 403);
        }

        $eventTalent = EventTalent::where('event_id', $event->id)->find($id);
        if (! $eventTalent) return $this->error('Lineup entry not found.', 404);

        $data = $request->validate([
            'talent_id'         => ['nullable', 'integer', 'exists:talents,id'],
            'free_name'         => ['nullable', 'string', 'max:150'],
            'role'              => ['sometimes', 'in:headliner,opening_act,dj,mc,special_guest,performer'],
            'performance_order' => ['nullable', 'integer', 'min:0'],
            'performance_time'  => ['nullable', 'string', 'max:50'],
        ]);

        $eventTalent->update($data);
        $eventTalent->load('talent');

        return $this->success('Lineup updated.', new EventTalentResource($eventTalent));
    }

    public function destroy(string $eventId, int $id): JsonResponse
    {
        $event = $this->events->findById($eventId);
        if (! $event) return $this->error('Event not found.', 404);

        if ($event->user_id !== auth('api')->id()) {
            return $this->error('Forbidden.', 403);
        }

        $eventTalent = EventTalent::where('event_id', $event->id)->find($id);
        if (! $eventTalent) return $this->error('Lineup entry not found.', 404);

        $eventTalent->delete();

        return $this->success('Talent removed from lineup.');
    }
}
