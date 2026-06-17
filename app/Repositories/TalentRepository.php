<?php

namespace App\Repositories;

use App\Models\Talent;
use App\Repositories\Contracts\TalentRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class TalentRepository implements TalentRepositoryInterface
{
    public function paginate(int $perPage, array $filters = []): LengthAwarePaginator
    {
        return Talent::where('is_active', true)
            ->where('is_verified', true)
            ->when($filters['search'] ?? null, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($filters['type'] ?? null, fn($q, $t) => $q->where('type', $t))
            ->when($filters['category'] ?? null, fn($q, $c) => $q->where('category', $c))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function paginateByUser(int $userId, int $perPage, array $filters = []): LengthAwarePaginator
    {
        return Talent::where('submitted_by', $userId)
            ->when($filters['search'] ?? null, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function paginateAdmin(int $perPage, array $filters = []): LengthAwarePaginator
    {
        return Talent::withTrashed()
            ->when($filters['search'] ?? null, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when(array_key_exists('is_verified', $filters), fn($q) => $q->where('is_verified', $filters['is_verified']))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findById(int $id): ?Talent
    {
        return Talent::find($id);
    }

    public function create(array $data): Talent
    {
        return Talent::create($data);
    }

    public function update(Talent $talent, array $data): Talent
    {
        $talent->update($data);
        return $talent->fresh();
    }

    public function delete(Talent $talent): void
    {
        $talent->delete();
    }

    public function isSlugTaken(string $slug, ?int $excludeId = null): bool
    {
        return Talent::where('slug', $slug)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }
}
