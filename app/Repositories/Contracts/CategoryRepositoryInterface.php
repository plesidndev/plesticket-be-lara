<?php

namespace App\Repositories\Contracts;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface CategoryRepositoryInterface
{
    public function allActive(): Collection;
    public function paginate(int $perPage, array $filters = []): LengthAwarePaginator;
    public function findById(int $id): ?Category;
    public function findByName(string $name, ?int $excludeId = null): ?Category;
    public function create(array $data): Category;
    public function update(Category $category, array $data): Category;
    public function delete(Category $category): void;
}
