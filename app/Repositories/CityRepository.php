<?php

namespace App\Repositories;

use App\Models\City;
use App\Repositories\Contracts\CityRepositoryInterface;
use Illuminate\Support\Collection;

class CityRepository implements CityRepositoryInterface
{
    public function all(?string $provinceCode = null): Collection
    {
        return City::when($provinceCode, fn($q) => $q->where('province_code', $provinceCode))
            ->orderBy('name')
            ->get();
    }
}