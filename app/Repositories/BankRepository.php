<?php

namespace App\Repositories;

use App\Models\Bank;
use App\Repositories\Contracts\BankRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class BankRepository implements BankRepositoryInterface
{
    public function all(): Collection
    {
        return Bank::where('is_active', true)->orderBy('name')->get();
    }

    public function findById(int $id): ?Bank
    {
        return Bank::find($id);
    }
}
