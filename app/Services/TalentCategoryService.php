<?php

namespace App\Services;

use App\Models\TalentCategory;
use App\Repositories\Contracts\TalentCategoryRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TalentCategoryService
{
    public function __construct(private readonly TalentCategoryRepositoryInterface $categories) {}

    public function list(bool $activeOnly = true): Collection
    {
        return $this->categories->all($activeOnly);
    }

    public function save(array $data, ?string $code = null): TalentCategory
    {
        $category = $code !== null ? $this->categories->find($code) : null;
        if (isset($data['name']) && $this->categories->nameExists($data['name'], $code)) {
            throw ValidationException::withMessages(['name' => 'This name already exists.']);
        }
        try {
            return $category ? $this->categories->update($category, $data) : $this->categories->create($data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'This code or name already exists.']);
        }
    }
}
