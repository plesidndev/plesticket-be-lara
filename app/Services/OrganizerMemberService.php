<?php

namespace App\Services;

use App\Enums\OrganizerRole;
use App\Enums\VerificationStatus;
use App\Models\Event;
use App\Models\OrganizerMember;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Repositories\Contracts\OrganizerMemberRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;
use RuntimeException;

class OrganizerMemberService
{
    public function __construct(
        private readonly OrganizerMemberRepositoryInterface $members,
        private readonly EventRepositoryInterface $events,
    ) {}

    private function resolveEvent(string $eventId, int $userId): Event
    {
        $event = $this->events->findById($eventId);

        if (! $event) {
            throw new RuntimeException('Event not found.');
        }

        if ($event->user_id !== $userId) {
            throw new RuntimeException('Event not found.');
        }

        if ($event->verification_status !== VerificationStatus::Verified) {
            throw new InvalidArgumentException('Organizer members can only be added to verified events.');
        }

        return $event;
    }

    public function list(string $eventId, int $userId, int $perPage = 15): LengthAwarePaginator
    {
        $this->resolveEvent($eventId, $userId);

        return $this->members->paginate($eventId, $perPage);
    }

    public function add(string $eventId, int $userId, array $data): OrganizerMember
    {
        $event = $this->resolveEvent($eventId, $userId);

        OrganizerRole::from($data['role']);

        if ($data['email'] && $this->members->findByEmail($eventId, $data['email'])) {
            throw new InvalidArgumentException('A member with this email already exists for this event.');
        }

        return $this->members->create([
            'owner_id'   => $userId,
            'event_id'   => $event->id,
            'event_code' => $event->event_id,
            'name'       => $data['name'],
            'email'      => $data['email'] ?? null,
            'password'   => $data['password'],
            'role'       => OrganizerRole::from($data['role']),
            'is_active'  => true,
        ]);
    }

    public function update(string $eventId, int $userId, int $memberId, array $data): OrganizerMember
    {
        $this->resolveEvent($eventId, $userId);

        $member = $this->members->findById($eventId, $memberId);

        if (! $member) {
            throw new RuntimeException('Member not found.');
        }

        if (isset($data['role'])) {
            OrganizerRole::from($data['role']);
        }

        return $this->members->update($member, $data);
    }

    public function remove(string $eventId, int $userId, int $memberId): void
    {
        $this->resolveEvent($eventId, $userId);

        $member = $this->members->findById($eventId, $memberId);

        if (! $member) {
            throw new RuntimeException('Member not found.');
        }

        $this->members->delete($member);
    }
}
