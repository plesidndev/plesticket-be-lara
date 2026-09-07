<?php

namespace App\Services\Catalog;

use App\Enums\CatalogStatus;
use App\Models\CatalogAsset;
use App\Models\CatalogRelease;
use App\Models\CatalogSubmission;
use App\Repositories\Contracts\CatalogRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CatalogService
{
    public function __construct(
        private readonly CatalogRepositoryInterface $catalog,
        private readonly CatalogMasterDataService $masters,
    ) {}

    public function list(?int $ownerId, array $filters): LengthAwarePaginator
    {
        return $this->catalog->paginate($ownerId, $filters);
    }

    public function show(string $id, ?int $ownerId): CatalogRelease
    {
        return $this->catalog->details($this->catalog->find($id, $ownerId));
    }

    public function create(int $ownerId, array $data): CatalogRelease
    {
        $metadata = $this->metadata($data['metadata'] ?? [], $data['type']);

        return DB::transaction(function () use ($ownerId, $data, $metadata) {
            $release = $this->catalog->create([
                'user_id' => $ownerId, 'type' => $data['type'], 'title' => $data['title'],
                'metadata' => $metadata, 'status' => CatalogStatus::Draft,
            ]);
            $this->catalog->activity($release, $ownerId, 'created');

            return $release;
        });
    }

    public function update(string $id, int $ownerId, array $data): CatalogRelease
    {
        return DB::transaction(function () use ($id, $ownerId, $data) {
            $release = $this->catalog->find($id, $ownerId, true);
            $this->editable($release);
            $changes = array_intersect_key($data, array_flip(['title', 'metadata']));
            if (array_key_exists('metadata', $changes)) {
                $changes['metadata'] = $this->metadata($changes['metadata'], $release->type);
                $trackIds = array_column($changes['metadata']['tracks'] ?? [], 'id');
                foreach ($this->catalog->assets($release) as $asset) {
                    if ($asset->kind === 'audio' && ! in_array($asset->track_id, $trackIds, true)) {
                        $this->catalog->retireAsset($asset);
                    }
                }
            }
            $this->catalog->update($release, $changes);
            $this->catalog->activity($release, $ownerId, 'draft_updated');

            return $this->catalog->details($release);
        });
    }

    public function delete(string $id, int $ownerId): void
    {
        $assets = DB::transaction(function () use ($id, $ownerId) {
            $release = $this->catalog->find($id, $ownerId, true);
            if ($release->status !== CatalogStatus::Draft || $release->version !== 0) {
                throw new ConflictHttpException('Only an unsubmitted draft can be deleted.');
            }
            $assets = $this->catalog->assets($release, false);
            $this->catalog->delete($release);

            return $assets;
        });
        foreach ($assets as $asset) {
            Storage::disk($asset->disk)->delete($asset->path);
        }
    }

    public function upload(string $id, int $ownerId, UploadedFile $file, array $data): CatalogRelease
    {
        $release = $this->catalog->find($id, $ownerId);
        $this->editable($release);
        $disk = (string) config('catalog.disk');
        $path = $file->store('releases/'.$id, ['disk' => $disk, 'visibility' => 'private']);
        if (! $path) {
            throw new \RuntimeException('Catalog file could not be stored.');
        }

        try {
            return DB::transaction(function () use ($id, $ownerId, $file, $data, $disk, $path) {
                $release = $this->catalog->find($id, $ownerId, true);
                $this->editable($release);
                $kind = $data['kind'];
                if (($kind === 'audio' && $release->type !== 'music') || ($kind === 'video' && $release->type !== 'music_video')) {
                    throw ValidationException::withMessages(['kind' => 'This asset type does not belong to this release.']);
                }
                if ($kind === 'audio' && ! in_array($data['track_id'], array_column($release->metadata['tracks'] ?? [], 'id'), true)) {
                    throw ValidationException::withMessages(['track_id' => 'Add the track to this release before uploading its audio.']);
                }
                $asset = $this->catalog->addAsset($release, [
                    'kind' => $kind, 'track_id' => $data['track_id'] ?? null,
                    'disk' => $disk, 'path' => $path,
                    'original_name' => mb_substr(preg_replace('/[\x00-\x1F\x7F]/', '', basename(str_replace('\\', '/', $file->getClientOriginalName()))), 0, 255),
                    'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                ]);
                $this->catalog->update($release, ['updated_at' => now()]);
                $this->catalog->activity($release, $ownerId, 'asset_uploaded', ['context' => ['asset_id' => $asset->id]]);

                return $this->catalog->details($release);
            });
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }
    }

    public function removeAsset(string $id, int $ownerId, string $assetId): void
    {
        DB::transaction(function () use ($id, $ownerId, $assetId) {
            $release = $this->catalog->find($id, $ownerId, true);
            $this->editable($release);
            $asset = $this->catalog->asset($release, $assetId);
            $this->catalog->retireAsset($asset);
            $this->catalog->activity($release, $ownerId, 'asset_removed', ['context' => ['asset_id' => $assetId]]);
        });
    }

    public function asset(string $id, ?int $ownerId, string $assetId): CatalogAsset
    {
        return $this->catalog->asset($this->catalog->find($id, $ownerId), $assetId);
    }

    public function submit(string $id, int $ownerId): CatalogRelease
    {
        return DB::transaction(function () use ($id, $ownerId) {
            $release = $this->catalog->find($id, $ownerId, true);
            $this->editable($release);
            $metadata = $this->metadata($release->metadata, $release->type, true);
            $assets = $this->catalog->assets($release);
            $errors = [];
            if ($release->type === 'music') {
                if (! $assets->contains('kind', 'artwork')) {
                    $errors['assets.artwork'] = 'Upload cover artwork before submitting.';
                }
                foreach ($metadata['tracks'] as $index => $track) {
                    if (! $assets->contains(fn ($asset) => $asset->kind === 'audio' && $asset->track_id === $track['id'])) {
                        $errors['metadata.tracks.'.$index.'.audio'] = 'Upload audio for this track.';
                    }
                }
            } elseif (! $assets->contains('kind', 'video')) {
                $errors['assets.video'] = 'Upload the music video before submitting.';
            }
            foreach ($assets as $asset) {
                if (! Storage::disk($asset->disk)->exists($asset->path)) {
                    $errors['assets.'.$asset->id] = 'The stored file is missing. Upload it again.';
                }
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
            $from = $release->status->value;
            $this->catalog->update($release, [
                'version' => $release->version + 1, 'status' => CatalogStatus::WaitingForReview,
                'submitted_at' => now(), 'distribution' => null,
                'has_change_request' => false,
                'metadata' => $metadata,
            ]);
            $this->catalog->submit($release, [
                'id' => $release->id, 'type' => $release->type, 'title' => $release->title,
                'metadata' => $metadata, 'assets' => $assets->map(fn ($asset) => $asset->only(['id', 'kind', 'track_id', 'original_name', 'mime_type', 'size', 'sha256']))->all(),
                'master_data' => $this->masters->snapshot($metadata),
            ]);
            foreach ($metadata['stores'] as $store) {
                $this->catalog->delivery($release, $store, ['status' => 'pending']);
            }
            $this->catalog->activity($release, $ownerId, 'submitted', ['from_status' => $from, 'to_status' => $release->status->value]);

            return $this->catalog->details($release);
        });
    }

    public function requestChange(string $id, int $ownerId, string $message): CatalogRelease
    {
        return DB::transaction(function () use ($id, $ownerId, $message) {
            $release = $this->catalog->find($id, $ownerId, true);
            if ($release->status->editable() || $release->status === CatalogStatus::Rejected) {
                throw new ConflictHttpException('A change request requires an active submitted release.');
            }
            if ($release->has_change_request) {
                throw new ConflictHttpException('A change request is already waiting for review.');
            }
            $this->catalog->update($release, ['has_change_request' => true]);
            $this->catalog->activity($release, $ownerId, 'change_requested', ['message' => $message]);

            return $this->catalog->details($release);
        });
    }

    public function transition(string $id, int $adminId, array $data): CatalogRelease
    {
        return DB::transaction(function () use ($id, $adminId, $data) {
            $release = $this->catalog->find($id, null, true);
            $this->version($release, $data['version']);
            $next = CatalogStatus::from($data['status']);
            if (! in_array($next, $release->status->next(), true)) {
                throw new ConflictHttpException('This status transition is not allowed.');
            }
            if ($next === CatalogStatus::ProcessingDistribution && empty($release->distribution['reference'])) {
                throw ValidationException::withMessages(['distribution.reference' => 'Record the distributor submission reference first.']);
            }
            if ($next === CatalogStatus::Live) {
                $deliveries = $this->catalog->deliveries($release);
                if ($deliveries->isEmpty() || $deliveries->contains(fn ($delivery) => $delivery->status !== 'live')) {
                    throw ValidationException::withMessages(['stores' => 'Confirm every selected store is live first. Individual store availability is tracked separately.']);
                }
            }
            $from = $release->status->value;
            if ($release->has_change_request && ! in_array($next, [CatalogStatus::ChangesRequested, CatalogStatus::Rejected], true)) {
                throw new ConflictHttpException('Resolve the customer change request before advancing distribution.');
            }
            $this->catalog->update($release, ['status' => $next, 'has_change_request' => false]);
            $this->catalog->activity($release, $adminId, 'status_changed', [
                'from_status' => $from, 'to_status' => $next->value,
                'message' => $data['message'] ?? null, 'fields' => $data['fields'] ?? null,
            ]);

            return $this->catalog->details($release);
        });
    }

    public function assign(string $id, int $adminId, ?int $assignee): CatalogRelease
    {
        return DB::transaction(function () use ($id, $adminId, $assignee) {
            $release = $this->catalog->find($id, null, true);
            if ($assignee !== null && ! $this->catalog->isActiveAdmin($assignee)) {
                throw ValidationException::withMessages(['assigned_admin_id' => 'Choose an active super admin.']);
            }
            $this->catalog->update($release, ['assigned_admin_id' => $assignee]);
            $this->catalog->activity($release, $adminId, 'assigned', ['internal' => true, 'context' => ['assigned_admin_id' => $assignee]]);

            return $this->catalog->details($release);
        });
    }

    public function note(string $id, int $adminId, string $message): CatalogRelease
    {
        return DB::transaction(function () use ($id, $adminId, $message) {
            $release = $this->catalog->find($id, null, true);
            $this->catalog->activity($release, $adminId, 'internal_note', ['internal' => true, 'message' => $message]);

            return $this->catalog->details($release);
        });
    }

    public function distribution(string $id, int $adminId, array $data): CatalogRelease
    {
        return DB::transaction(function () use ($id, $adminId, $data) {
            $release = $this->catalog->find($id, null, true);
            $this->version($release, $data['version']);
            if (! in_array($release->status, [CatalogStatus::Approved, CatalogStatus::ProcessingDistribution, CatalogStatus::Scheduled, CatalogStatus::Live], true)) {
                throw new ConflictHttpException('Approve the current submission before recording distribution.');
            }
            if ($release->has_change_request) {
                throw new ConflictHttpException('Resolve the customer change request before recording a distribution submission.');
            }
            foreach ($data['isrcs'] ?? [] as $identifier) {
                if (! in_array($identifier['track_id'], array_column($release->metadata['tracks'] ?? [], 'id'), true)) {
                    throw ValidationException::withMessages(['isrcs' => 'Every ISRC must reference a track in this release.']);
                }
            }
            if (! empty($data['video_isrc']) && $release->type !== 'music_video') {
                throw ValidationException::withMessages(['video_isrc' => 'A video ISRC belongs to a music video release.']);
            }
            $this->catalog->update($release, ['distribution' => $data]);
            $this->catalog->activity($release, $adminId, 'distribution_recorded', ['internal' => true, 'context' => $data]);

            return $this->catalog->details($release);
        });
    }

    public function delivery(string $id, int $adminId, string $store, array $data): CatalogRelease
    {
        return DB::transaction(function () use ($id, $adminId, $store, $data) {
            $release = $this->catalog->find($id, null, true);
            $this->version($release, $data['version']);
            if (! in_array($release->status, [CatalogStatus::ProcessingDistribution, CatalogStatus::Scheduled, CatalogStatus::Live], true)) {
                throw new ConflictHttpException('Store updates require a release submitted for distribution.');
            }
            if (! in_array($store, $release->metadata['stores'], true)) {
                throw ValidationException::withMessages(['store' => 'This store is not selected for this submission.']);
            }
            $this->catalog->delivery($release, $store, [
                'status' => $data['status'], 'url' => $data['url'] ?? null,
                'live_at' => $data['status'] === 'live' ? now() : null,
            ]);
            // A removed/failed store must not leave the release claiming all stores are live.
            if ($release->status === CatalogStatus::Live && $data['status'] !== 'live') {
                $this->catalog->update($release, ['status' => CatalogStatus::ProcessingDistribution]);
                $this->catalog->activity($release, $adminId, 'status_changed', ['from_status' => 'live', 'to_status' => 'processing_distribution']);
            }
            $this->catalog->activity($release, $adminId, 'store_updated', ['message' => $store.': '.$data['status']]);

            return $this->catalog->details($release);
        });
    }

    public function submission(string $id, ?int $ownerId, int $version): CatalogSubmission
    {
        return $this->catalog->submission($this->catalog->find($id, $ownerId), $version);
    }

    private function editable(CatalogRelease $release): void
    {
        if (! $release->status->editable()) {
            throw new ConflictHttpException('This submission is locked. Request changes before editing.');
        }
    }

    private function version(CatalogRelease $release, int $version): void
    {
        if ($version !== $release->version || $version < 1) {
            throw new ConflictHttpException('The submission version has changed. Refresh before continuing.');
        }
    }

    private function metadata(array $metadata, string $type, bool $complete = false): array
    {
        $validated = Validator::make(['metadata' => $metadata], CatalogMetadataRules::rules($complete, $type))->validate()['metadata'] ?? [];
        $validated = $this->masters->normalize($validated);
        $errors = [];
        if (in_array('WORLD', $validated['territories'] ?? [], true) && count($validated['territories']) !== 1) {
            $errors['metadata.territories'] = 'Use WORLD alone or select individual countries.';
        }
        if ($complete) {
            $artists = ['metadata.artists' => $validated['artists']];
            $contributors = $type === 'music_video' ? ['metadata.contributors' => $validated['contributors']] : [];
            foreach ($validated['tracks'] ?? [] as $index => $track) {
                $artists['metadata.tracks.'.$index.'.artists'] = $track['artists'];
                $contributors['metadata.tracks.'.$index.'.contributors'] = $track['contributors'];
            }
            foreach ($artists as $field => $people) {
                if (! in_array('primary', array_column($people, 'role'), true)) {
                    $errors[$field] = 'At least one primary artist is required.';
                }
            }
            foreach ($contributors as $field => $people) {
                if (! array_intersect(['songwriter', 'composer'], array_column($people, 'role'))) {
                    $errors[$field] = 'At least one songwriter or composer is required.';
                }
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $validated;
    }
}
