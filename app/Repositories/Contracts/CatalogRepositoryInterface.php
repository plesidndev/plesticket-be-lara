<?php

namespace App\Repositories\Contracts;

use App\Models\CatalogAsset;
use App\Models\CatalogRelease;
use App\Models\CatalogSubmission;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CatalogRepositoryInterface
{
    public function paginate(?int $ownerId, array $filters): LengthAwarePaginator;

    public function find(string $id, ?int $ownerId, bool $lock = false): CatalogRelease;

    public function details(CatalogRelease $release): CatalogRelease;

    public function create(array $data): CatalogRelease;

    public function update(CatalogRelease $release, array $data): CatalogRelease;

    public function delete(CatalogRelease $release): void;

    public function assets(CatalogRelease $release, bool $currentOnly = true): Collection;

    public function asset(CatalogRelease $release, string $id): CatalogAsset;

    public function addAsset(CatalogRelease $release, array $data): CatalogAsset;

    public function retireAsset(CatalogAsset $asset): void;

    public function submit(CatalogRelease $release, array $snapshot): CatalogSubmission;

    public function submission(CatalogRelease $release, int $version): CatalogSubmission;

    public function activity(CatalogRelease $release, int $actorId, string $action, array $data = []): void;

    public function deliveries(CatalogRelease $release): Collection;

    public function delivery(CatalogRelease $release, string $store, array $data): void;

    public function isActiveAdmin(int $id): bool;
}
