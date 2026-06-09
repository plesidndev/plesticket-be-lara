<?php

namespace App\Repositories;

use App\Models\Event;
use App\Repositories\Contracts\EventRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class EventRepository implements EventRepositoryInterface
{
    public function paginatePublic(int $perPage, array $filters = []): LengthAwarePaginator
    {
        $query = Event::with(['user', 'ticketTypes'])
            ->where('verification_status', 'verified')
            ->where('show_status', true);

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['city'])) {
            $query->where('city', 'ilike', '%' . $filters['city'] . '%');
        }

        if (isset($filters['is_online'])) {
            $query->where('is_online', $filters['is_online']);
        }

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'ilike', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'ilike', '%' . $filters['search'] . '%');
            });
        }

        match($filters['sort'] ?? 'upcoming') {
            'date_desc' => $query->orderByDesc('start_date'),
            'newest'    => $query->orderByDesc('created_at'),
            default     => $query->orderBy('start_date', 'asc'), // upcoming: soonest first
        };

        return $query->paginate($perPage);
    }

    public function paginateAdmin(int $perPage, array $filters = []): LengthAwarePaginator
    {
        $query = Event::with(['user', 'verifiedBy'])->orderByDesc('created_at');

        if (! empty($filters['verification_status'])) {
            $query->where('verification_status', $filters['verification_status']);
        }

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'ilike', '%' . $filters['search'] . '%')
                  ->orWhere('event_id', 'ilike', '%' . $filters['search'] . '%');
            });
        }

        return $query->paginate($perPage);
    }

    public function paginateByUser(int $userId, int $perPage): LengthAwarePaginator
    {
        return Event::with('ticketTypes')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findById(string $id): ?Event
    {
        $query = Event::with(['user', 'verifiedBy', 'ticketTypes']);

        // UUID primary key vs event_id string (e.g. EVT0032)
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
            return $query->find($id);
        }

        return $query->where('event_id', $id)->first();
    }

    public function findBySlug(string $slug): ?Event
    {
        return Event::with(['user', 'ticketTypes'])
            ->where('slug', $slug)
            ->where('verification_status', 'verified')
            ->where('show_status', true)
            ->first();
    }

    public function create(array $data): Event
    {
        $data['event_id'] = sprintf('EVT%04d', Event::withTrashed()->count() + 1);
        $event = Event::create($data);
        return $event->fresh();
    }

    public function update(Event $event, array $data): Event
    {
        $event->update($data);
        return $event->fresh(['user', 'verifiedBy']);
    }

    public function delete(Event $event): void
    {
        $event->delete();
    }

    public function isSlugTaken(string $slug, ?string $excludeId = null): bool
    {
        return Event::where('slug', $slug)
            ->when($excludeId, function ($q) use ($excludeId) {
                $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $excludeId);
                $q->where($isUuid ? 'id' : 'event_id', '!=', $excludeId);
            })
            ->exists();
    }
}
