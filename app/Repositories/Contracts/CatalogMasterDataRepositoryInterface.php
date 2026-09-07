<?php

namespace App\Repositories\Contracts;

use App\Models\CatalogMasterEntry;
use Illuminate\Support\Collection;

interface CatalogMasterDataRepositoryInterface
{
    public function all(string $type, bool $activeOnly): Collection;

    public function find(string $type, string $code): CatalogMasterEntry;

    public function create(string $type, array $data): CatalogMasterEntry;

    public function update(CatalogMasterEntry $entry, array $data): CatalogMasterEntry;

    public function nameExists(string $type, string $name, ?string $exceptCode = null): bool;
}
