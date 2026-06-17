<?php

namespace App\Services;

use App\Models\Talent;
use App\Repositories\Contracts\TalentRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class TalentService
{
    public function __construct(private readonly TalentRepositoryInterface $talents) {}

    public function list(int $perPage, array $filters): LengthAwarePaginator
    {
        return $this->talents->paginate($perPage, $filters);
    }

    public function mine(int $userId, int $perPage, array $filters): LengthAwarePaginator
    {
        return $this->talents->paginateByUser($userId, $perPage, $filters);
    }

    public function adminList(int $perPage, array $filters): LengthAwarePaginator
    {
        return $this->talents->paginateAdmin($perPage, $filters);
    }

    public function create(int $userId, array $data): Talent
    {
        $slug = $this->generateSlug($data['name'], $data['slug'] ?? null);

        return $this->talents->create([
            ...$data,
            'slug'         => $slug,
            'submitted_by' => $userId,
        ]);
    }

    public function update(int $talentId, int $userId, bool $isAdmin, array $data): Talent
    {
        $talent = $this->findOrFail($talentId);

        if (! $isAdmin && $talent->submitted_by !== $userId) {
            throw new RuntimeException('You are not allowed to update this talent.');
        }

        if (isset($data['slug']) && $data['slug'] !== $talent->slug) {
            $data['slug'] = $this->generateSlug($data['name'] ?? $talent->name, $data['slug']);
        } elseif (isset($data['name']) && $data['name'] !== $talent->name && ! isset($data['slug'])) {
            // Auto-regenerate slug on name change only if no explicit slug given
        }

        return $this->talents->update($talent, $data);
    }

    public function verify(int $talentId, int $adminId): Talent
    {
        $talent = $this->findOrFail($talentId);

        if ($talent->is_verified) {
            throw new InvalidArgumentException('Talent is already verified.');
        }

        return $this->talents->update($talent, [
            'is_verified' => true,
            'verified_by' => $adminId,
        ]);
    }

    public function delete(int $talentId, int $userId, bool $isAdmin): void
    {
        $talent = $this->findOrFail($talentId);

        if (! $isAdmin && $talent->submitted_by !== $userId) {
            throw new RuntimeException('You are not allowed to delete this talent.');
        }

        $this->talents->delete($talent);
    }

    public function findOrFail(int $id): Talent
    {
        $talent = $this->talents->findById($id);

        if (! $talent) {
            throw new RuntimeException('Talent not found.');
        }

        return $talent;
    }

    private function generateSlug(string $name, ?string $base = null): string
    {
        $base = $base ? Str::slug($base) : Str::slug($name);
        $slug = $base;
        $i    = 1;

        while ($this->talents->isSlugTaken($slug)) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
