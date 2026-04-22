<?php

namespace App\Repositories\Contracts;

use App\Models\OrganizerMember;
use Illuminate\Pagination\LengthAwarePaginator;

interface OrganizerMemberRepositoryInterface
{
    public function paginate(string $eventId, int $perPage): LengthAwarePaginator;
    public function findById(string $eventId, int $id): ?OrganizerMember;
    public function findByUid(string $uid): ?OrganizerMember;
    public function findByEmail(string $eventId, string $email): ?OrganizerMember;
    public function countByEvent(string $eventId): int;
    public function create(array $data): OrganizerMember;
    public function update(OrganizerMember $member, array $data): OrganizerMember;
    public function delete(OrganizerMember $member): void;
}
