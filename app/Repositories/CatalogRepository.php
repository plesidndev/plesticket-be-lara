<?php

namespace App\Repositories;

use App\Models\CatalogAsset;
use App\Models\CatalogRelease;
use App\Models\CatalogSubmission;
use App\Models\User;
use App\Repositories\Contracts\CatalogRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CatalogRepository implements CatalogRepositoryInterface
{
    public function paginate(?int $ownerId, array $filters): LengthAwarePaginator
    {
        return CatalogRelease::query()
            ->when($ownerId !== null, fn ($q) => $q->where('user_id', $ownerId))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['assigned_admin_id'] ?? null, fn ($q, $id) => $q->where('assigned_admin_id', $id))
            ->when(array_key_exists('has_change_request', $filters), fn ($q) => $q->where('has_change_request', $filters['has_change_request']))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower($search).'%']))
            ->latest('updated_at')->orderBy('id')->paginate($filters['limit'] ?? 20);
    }

    public function find(string $id, ?int $ownerId, bool $lock = false): CatalogRelease
    {
        $query = CatalogRelease::whereKey($id)->when($ownerId !== null, fn ($q) => $q->where('user_id', $ownerId));
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    public function create(array $data): CatalogRelease
    {
        return CatalogRelease::create($data);
    }

    public function details(CatalogRelease $release): CatalogRelease
    {
        return $release->load([
            'assets' => fn ($q) => $q->where('is_current', true),
            'submissions' => fn ($q) => $q->orderByDesc('version'),
            'activities' => fn ($q) => $q->orderBy('id'),
            'deliveries' => fn ($q) => $q->where('version', $release->version),
        ]);
    }

    public function update(CatalogRelease $release, array $data): CatalogRelease
    {
        $release->update($data);

        return $release;
    }

    public function delete(CatalogRelease $release): void
    {
        $release->delete();
    }

    public function assets(CatalogRelease $release, bool $currentOnly = true): Collection
    {
        return $release->assets()->when($currentOnly, fn ($q) => $q->where('is_current', true))->orderBy('created_at')->get();
    }

    public function asset(CatalogRelease $release, string $id): CatalogAsset
    {
        return $release->assets()->whereKey($id)->firstOrFail();
    }

    public function addAsset(CatalogRelease $release, array $data): CatalogAsset
    {
        $release->assets()->where('kind', $data['kind'])->where('track_id', $data['track_id'])->update(['is_current' => false]);

        return $release->assets()->create($data);
    }

    public function retireAsset(CatalogAsset $asset): void
    {
        $asset->update(['is_current' => false]);
    }

    public function submit(CatalogRelease $release, array $snapshot): CatalogSubmission
    {
        return $release->submissions()->create(['version' => $release->version, 'snapshot' => $snapshot, 'created_at' => now()]);
    }

    public function submission(CatalogRelease $release, int $version): CatalogSubmission
    {
        return $release->submissions()->where('version', $version)->firstOrFail();
    }

    public function activity(CatalogRelease $release, int $actorId, string $action, array $data = []): void
    {
        $release->activities()->create(array_merge($data, [
            'actor_id' => $actorId, 'action' => $action, 'version' => $release->version, 'created_at' => now(),
        ]));
    }

    public function deliveries(CatalogRelease $release): Collection
    {
        return $release->deliveries()->where('version', $release->version)->get();
    }

    public function delivery(CatalogRelease $release, string $store, array $data): void
    {
        $release->deliveries()->updateOrCreate(['version' => $release->version, 'store' => $store], $data);
    }

    public function isActiveAdmin(int $id): bool
    {
        return User::whereKey($id)->where('role', 'SUPER_ADMIN')->where('is_active', true)->exists();
    }
}
