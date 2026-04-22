<?php

namespace App\Repositories;

use App\Models\OrganizerMember;
use App\Repositories\Contracts\OrganizerMemberRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class OrganizerMemberRepository implements OrganizerMemberRepositoryInterface
{
    public function paginate(string $eventId, int $perPage): LengthAwarePaginator
    {
        return OrganizerMember::where('event_id', $eventId)
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function findById(string $eventId, int $id): ?OrganizerMember
    {
        return OrganizerMember::where('event_id', $eventId)->find($id);
    }

    public function findByUid(string $uid): ?OrganizerMember
    {
        return OrganizerMember::where('uid', $uid)->first();
    }

    public function findByEmail(string $eventId, string $email): ?OrganizerMember
    {
        return OrganizerMember::where('event_id', $eventId)
            ->where('email', $email)
            ->first();
    }

    public function countByEvent(string $eventId): int
    {
        return OrganizerMember::where('event_id', $eventId)->count();
    }

    public function create(array $data): OrganizerMember
    {
        $member   = OrganizerMember::create($data);
        $sequence = $this->countByEvent($data['event_id']);

        // UID: {human-readable event_id}-{sequence:04d} e.g. EVT0001-0001
        $member->update(['uid' => sprintf('%s-%04d', $data['event_code'], $sequence)]);

        return $member->fresh();
    }

    public function update(OrganizerMember $member, array $data): OrganizerMember
    {
        $member->update($data);
        return $member->fresh();
    }

    public function delete(OrganizerMember $member): void
    {
        $member->delete();
    }
}
