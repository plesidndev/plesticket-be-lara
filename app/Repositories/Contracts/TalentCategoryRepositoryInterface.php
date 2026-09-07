<?php

namespace App\Repositories\Contracts;

use App\Models\TalentCategory;
use Illuminate\Support\Collection;

interface TalentCategoryRepositoryInterface
{
    public function all(bool $activeOnly): Collection;

    public function find(string $code): TalentCategory;

    public function create(array $data): TalentCategory;

    public function update(TalentCategory $category, array $data): TalentCategory;

    public function nameExists(string $name, ?string $exceptCode = null): bool;
}
