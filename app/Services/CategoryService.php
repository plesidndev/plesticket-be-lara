<?php

namespace App\Services;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;
use RuntimeException;

class CategoryService
{
    public function __construct(private readonly CategoryRepositoryInterface $repo) {}

    public function listActive(): Collection
    {
        return $this->repo->allActive();
    }

    public function listAdmin(int $perPage, array $filters = []): LengthAwarePaginator
    {
        return $this->repo->paginate($perPage, $filters);
    }

    public function create(array $data): Category
    {
        if ($this->repo->findByName($data['name'])) {
            throw new InvalidArgumentException('Category name already exists.');
        }

        return $this->repo->create([
            'name'      => $data['name'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(int $id, array $data): Category
    {
        $category = $this->repo->findById($id);

        if (! $category) {
            throw new RuntimeException('Category not found.');
        }

        if (isset($data['name']) && $this->repo->findByName($data['name'], $id)) {
            throw new InvalidArgumentException('Category name already exists.');
        }

        return $this->repo->update($category, $data);
    }

    public function delete(int $id): void
    {
        $category = $this->repo->findById($id);

        if (! $category) {
            throw new RuntimeException('Category not found.');
        }

        $this->repo->delete($category);
    }
}
