<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface ProvinceRepositoryInterface
{
    public function all(): Collection;
}