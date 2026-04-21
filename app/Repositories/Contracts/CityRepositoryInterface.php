<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface CityRepositoryInterface
{
    public function all(?string $provinceCode = null): Collection;
}