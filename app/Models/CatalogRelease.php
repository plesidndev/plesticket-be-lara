<?php

namespace App\Models;

use App\Enums\CatalogStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogRelease extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $attributes = ['version' => 0, 'has_change_request' => false];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'distribution' => 'array', 'status' => CatalogStatus::class, 'submitted_at' => 'datetime', 'version' => 'integer', 'has_change_request' => 'boolean'];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(CatalogAsset::class, 'release_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(CatalogSubmission::class, 'release_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CatalogActivity::class, 'release_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(CatalogStoreDelivery::class, 'release_id');
    }
}
