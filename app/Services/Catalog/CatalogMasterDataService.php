<?php

namespace App\Services\Catalog;

use App\Models\CatalogMasterEntry;
use App\Repositories\Contracts\CatalogMasterDataRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CatalogMasterDataService
{
    public function __construct(private readonly CatalogMasterDataRepositoryInterface $masters) {}

    public function list(string $type, bool $activeOnly = true): Collection
    {
        return $this->masters->all($type, $activeOnly);
    }

    public function uploadDspLogo(string $code, UploadedFile $logo): CatalogMasterEntry
    {
        $entry = $this->masters->find('dsps', $code);
        $disk = Storage::disk('public');
        $path = $disk->putFile('catalog/dsps', $logo);
        if ($path === false) {
            throw new \RuntimeException('Unable to store DSP logo.');
        }
        $oldPath = $entry->logo_path;
        try {
            $entry = $this->masters->update($entry, ['logo_path' => $path]);
        } catch (\Throwable $error) {
            $disk->delete($path);
            throw $error;
        }
        if ($oldPath) {
            $disk->delete($oldPath);
        }

        return $entry;
    }

    public function destinationCodes(string $type): array
    {
        return $this->list('dsps')->filter(fn ($entry) => in_array($type, $entry->supported_types, true))->pluck('code')->values()->all();
    }

    public function save(string $type, array $data, ?string $code = null): CatalogMasterEntry
    {
        $entry = $code !== null ? $this->masters->find($type, $code) : null;
        if (isset($data['name']) && $this->masters->nameExists($type, $data['name'], $code)) {
            throw ValidationException::withMessages(['name' => 'This name already exists.']);
        }
        try {
            return $entry ? $this->masters->update($entry, $data) : $this->masters->create($type, $data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'This code or name already exists.']);
        }
    }

    /** Validate active entries and normalize legacy display names to stable codes. */
    public function normalize(array $metadata): array
    {
        $lists = ['genres' => $this->list('genres'), 'languages' => $this->list('languages'), 'territories' => $this->list('territories'), 'dsps' => $this->list('dsps'), 'timezones' => $this->list('timezones')];
        $errors = [];
        foreach ($this->fields($metadata) as $path => $type) {
            $value = data_get($metadata, $path);
            if ($value === null) {
                continue;
            }
            $entry = in_array($type, ['territories', 'timezones', 'dsps'], true) ? $lists[$type]->firstWhere('code', $value) : $this->resolve($lists[$type], $value);
            if (! $entry) {
                $label = match ($type) {
                    'genres' => 'genre', 'languages' => 'language', 'territories' => 'territory', 'timezones' => 'timezone', 'dsps' => 'DSP'
                };
                $errors['metadata.'.$path] = 'Select an active '.$label.' from the catalog master data.';
            } else {
                data_set($metadata, $path, $entry->code);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $metadata;
    }

    /** Capture labels with each submission so master-data edits cannot rewrite history. */
    public function snapshot(array $metadata): array
    {
        $lists = ['genres' => $this->list('genres', false), 'languages' => $this->list('languages', false), 'territories' => $this->list('territories', false), 'dsps' => $this->list('dsps', false), 'timezones' => $this->list('timezones', false)];
        $result = [];
        foreach ($this->fields($metadata) as $path => $type) {
            $value = data_get($metadata, $path);
            $entry = $value === null ? null : (in_array($type, ['territories', 'timezones', 'dsps'], true) ? $lists[$type]->firstWhere('code', $value) : $this->resolve($lists[$type], $value));
            if ($entry) {
                $result[] = ['field' => 'metadata.'.$path, 'code' => $entry->code, 'name' => $entry->name];
            }
        }

        return $result;
    }

    private function resolve(Collection $entries, string $value): ?CatalogMasterEntry
    {
        return $entries->firstWhere('code', $value)
            ?? $entries->first(fn ($entry) => mb_strtolower($entry->name) === mb_strtolower($value));
    }

    private function fields(array $metadata): array
    {
        $fields = ['primary_genre' => 'genres', 'secondary_genre' => 'genres', 'language' => 'languages', 'timezone' => 'timezones'];
        foreach ($metadata['tracks'] ?? [] as $index => $track) {
            $fields['tracks.'.$index.'.language'] = 'languages';
        }
        foreach ($metadata['territories'] ?? [] as $index => $territory) {
            $fields['territories.'.$index] = 'territories';
        }

        foreach ($metadata['stores'] ?? [] as $index => $store) {
            $fields['stores.'.$index] = 'dsps';
        }

        return $fields;
    }
}
