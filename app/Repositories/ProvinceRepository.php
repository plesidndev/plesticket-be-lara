<?php

namespace App\Repositories;

use App\Models\Province;
use App\Repositories\Contracts\ProvinceRepositoryInterface;
use Illuminate\Support\Collection;

class ProvinceRepository implements ProvinceRepositoryInterface
{
    public function all(): Collection
    {
        return Province::orderBy('name')->get();
    }
}