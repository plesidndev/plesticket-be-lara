<?php

namespace App\Services;

use App\Enums\VerificationStatus;
use App\Models\Event;
use App\Repositories\Contracts\EventRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class EventService
{
    public function __construct(
        private readonly EventRepositoryInterface $events,
    ) {}

    public function listPublic(int $perPage, array $filters = []): LengthAwarePaginator
    {
        return $this->events->paginatePublic($perPage, $filters);
    }

    public function listAdmin(int $perPage, array $filters = []): LengthAwarePaginator
    {
        return $this->events->paginateAdmin($perPage, $filters);
    }

    public function listByUser(int $userId, int $perPage): LengthAwarePaginator
    {
        return $this->events->paginateByUser($userId, $perPage);
    }

    public function findById(string $id): Event
    {
        $event = $this->events->findById($id);

        if (! $event) {
            throw new RuntimeException('Event not found.');
        }

        return $event;
    }

    public function findBySlug(string $slug): Event
    {
        $event = $this->events->findBySlug($slug);

        if (! $event) {
            throw new RuntimeException('Event not found.');
        }

        return $event;
    }

    public function create(int $userId, array $data): Event
    {
        $data['slug']    = $this->generateUniqueSlug($data['title'], $data['slug'] ?? null);
        $data['user_id'] = $userId;
        $data['verification_status'] = VerificationStatus::Pending;

        return $this->events->create($data);
    }

    public function update(string $id, int $userId, array $data): Event
    {
        $event = $this->findById($id);

        if ($event->user_id !== $userId) {
            throw new RuntimeException('Event not found.');
        }

        if ($event->verification_status === VerificationStatus::Verified) {
            throw new InvalidArgumentException('Verified events cannot be edited. Contact support.');
        }

        if (isset($data['title']) || isset($data['slug'])) {
            $newSlug = isset($data['slug'])
                ? Str::slug($data['slug'])
                : Str::slug($data['title'] ?? $event->title);

            $data['slug'] = $this->generateUniqueSlug($data['title'] ?? $event->title, $newSlug, $id);
        }

        return $this->events->update($event, $data);
    }

    public function verify(string $id, int $adminId): Event
    {
        $event = $this->findById($id);

        if ($event->verification_status === VerificationStatus::Verified) {
            throw new InvalidArgumentException('Already verified.');
        }

        return $this->events->update($event, [
            'verification_status' => VerificationStatus::Verified,
            'verified_at'         => now(),
            'verified_by'         => $adminId,
            'rejection_reason'    => null,
        ]);
    }

    public function reject(string $id, int $adminId, string $reason): Event
    {
        $event = $this->findById($id);

        return $this->events->update($event, [
            'verification_status' => VerificationStatus::Rejected,
            'rejection_reason'    => $reason,
            'verified_at'         => null,
            'verified_by'         => $adminId,
        ]);
    }

    public function suspend(string $id, int $adminId): Event
    {
        $event = $this->findById($id);

        return $this->events->update($event, [
            'verification_status' => VerificationStatus::Suspended,
            'verified_by'         => $adminId,
        ]);
    }

    public function delete(string $id, int $userId): void
    {
        $event = $this->findById($id);

        if ($event->user_id !== $userId) {
            throw new RuntimeException('Event not found.');
        }

        $this->events->delete($event);
    }

    private function generateUniqueSlug(string $title, ?string $customSlug, ?string $excludeId = null): string
    {
        $base = Str::slug($customSlug ?? $title);
        $slug = $base;
        $i    = 1;

        while ($this->events->isSlugTaken($slug, $excludeId)) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
