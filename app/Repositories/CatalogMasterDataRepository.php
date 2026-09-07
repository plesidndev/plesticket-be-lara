<?php

namespace App\Repositories;

use App\Models\CatalogGenre;
use App\Models\CatalogLanguage;
use App\Models\CatalogMasterEntry;
use App\Models\CatalogTerritory;
use App\Models\CatalogTimezone;
use App\Repositories\Contracts\CatalogMasterDataRepositoryInterface;
use Illuminate\Support\Collection;

class CatalogMasterDataRepository implements CatalogMasterDataRepositoryInterface
{
    public function all(string $type, bool $activeOnly): Collection
    {
        return $this->model($type)::query()->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    public function find(string $type, string $code): CatalogMasterEntry
    {
        return $this->model($type)::findOrFail($code);
    }

    public function create(string $type, array $data): CatalogMasterEntry
    {
        return $this->model($type)::create($data);
    }

    public function update(CatalogMasterEntry $entry, array $data): CatalogMasterEntry
    {
        $entry->update($data);

        return $entry;
    }

    public function nameExists(string $type, string $name, ?string $exceptCode = null): bool
    {
        return $this->model($type)::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($exceptCode !== null, fn ($query) => $query->where('code', '!=', $exceptCode))->exists();
    }

    private function model(string $type): string
    {
        return match ($type) {
            'genres' => CatalogGenre::class,
            'languages' => CatalogLanguage::class,
            'territories' => CatalogTerritory::class,
            'timezones' => CatalogTimezone::class,
            default => throw new \InvalidArgumentException('Unknown catalog master data type.'),
        };
    }
}
