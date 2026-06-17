<?php

namespace App\Repositories\Contracts;

use App\Models\Talent;
use Illuminate\Pagination\LengthAwarePaginator;

interface TalentRepositoryInterface
{
    public function paginate(int $perPage, array $filters = []): LengthAwarePaginator;
    public function paginateByUser(int $userId, int $perPage, array $filters = []): LengthAwarePaginator;
    public function paginateAdmin(int $perPage, array $filters = []): LengthAwarePaginator;
    public function findById(int $id): ?Talent;
    public function create(array $data): Talent;
    public function update(Talent $talent, array $data): Talent;
    public function delete(Talent $talent): void;
    public function isSlugTaken(string $slug, ?int $excludeId = null): bool;
}
