<?php

namespace App\Repositories;

use App\Models\TalentCategory;
use App\Repositories\Contracts\TalentCategoryRepositoryInterface;
use Illuminate\Support\Collection;

class TalentCategoryRepository implements TalentCategoryRepositoryInterface
{
    public function all(bool $activeOnly): Collection
    {
        return TalentCategory::query()->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    public function find(string $code): TalentCategory
    {
        return TalentCategory::findOrFail($code);
    }

    public function create(array $data): TalentCategory
    {
        return TalentCategory::create($data);
    }

    public function update(TalentCategory $category, array $data): TalentCategory
    {
        $category->update($data);

        return $category;
    }

    public function nameExists(string $name, ?string $exceptCode = null): bool
    {
        return TalentCategory::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($exceptCode !== null, fn ($query) => $query->where('code', '!=', $exceptCode))->exists();
    }
}
