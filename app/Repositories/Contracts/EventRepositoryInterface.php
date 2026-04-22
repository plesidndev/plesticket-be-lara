<?php

namespace App\Repositories\Contracts;

use App\Models\Event;
use Illuminate\Pagination\LengthAwarePaginator;

interface EventRepositoryInterface
{
    public function paginatePublic(int $perPage, array $filters = []): LengthAwarePaginator;
    public function paginateAdmin(int $perPage, array $filters = []): LengthAwarePaginator;
    public function paginateByUser(int $userId, int $perPage): LengthAwarePaginator;
    public function findById(string $id): ?Event;
    public function findBySlug(string $slug): ?Event;
    public function create(array $data): Event;
    public function update(Event $event, array $data): Event;
    public function delete(Event $event): void;
    public function isSlugTaken(string $slug, ?string $excludeId = null): bool;
}
