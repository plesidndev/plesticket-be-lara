<?php

namespace App\Services\Catalog;

use App\Repositories\Contracts\CatalogRepositoryInterface;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class CatalogExportService
{
    public function __construct(private readonly CatalogRepositoryInterface $catalog) {}

    /** Returns a temporary ZIP path; the HTTP response deletes it after download. */
    public function build(string $id, int $version, int $adminId): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required for catalog exports.');
        }
        $release = $this->catalog->find($id, null);
        $submission = $this->catalog->submission($release, $version);
        $snapshot = $submission->snapshot;
        $path = tempnam(sys_get_temp_dir(), 'catalog-export-');
        if ($path === false) {
            throw new RuntimeException('Could not create the export archive.');
        }
        $zip = new ZipArchive;
        $temporaryAssets = [];
        $opened = false;
        try {
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not open the export archive.');
            }
            $opened = true;
            $zip->addFromString('metadata.json', json_encode(['version' => $version, ...$snapshot], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $rows = [];
            $this->flatten(['version' => $version, ...$snapshot], '', $rows);
            $csv = fopen('php://temp', 'w+');
            fputcsv($csv, ['Field', 'Value'], ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($csv, $row, ',', '"', '');
            }
            rewind($csv);
            $zip->addFromString('metadata.csv', stream_get_contents($csv));
            fclose($csv);
            foreach ($snapshot['assets'] as $entry) {
                $asset = $this->catalog->asset($release, $entry['id']);
                $stream = Storage::disk($asset->disk)->readStream($asset->path);
                if (! is_resource($stream)) {
                    throw new RuntimeException('An original catalog asset is unavailable.');
                }
                $temporary = tempnam(sys_get_temp_dir(), 'catalog-asset-');
                if ($temporary === false) {
                    fclose($stream);
                    throw new RuntimeException('Could not stage an export asset.');
                }
                $temporaryAssets[] = $temporary;
                $output = fopen($temporary, 'wb');
                try {
                    if (stream_copy_to_stream($stream, $output) === false) {
                        throw new RuntimeException('Could not copy an export asset.');
                    }
                } finally {
                    fclose($stream);
                    fclose($output);
                }
                if (hash_file('sha256', $temporary) !== $entry['sha256']) {
                    throw new RuntimeException('A catalog asset no longer matches the submitted file.');
                }
                $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $entry['original_name']);
                $zip->addFile($temporary, 'assets/'.$entry['id'].'-'.$name);
            }
            $zip->addFromString('README.txt', "PlesConnect catalog submission v{$version}\nUse metadata.csv for manual entry and metadata.json for structured reference.\nAsset filenames start with the asset ID listed in the metadata.\nThis export is a saved submission, not a live editable draft.\nConfirm audio/video technical specifications and rights before distribution.\n");
            if (! $zip->close()) {
                throw new RuntimeException('Could not finish the export archive.');
            }
            $opened = false;
            $this->catalog->activity($release, $adminId, 'exported', ['internal' => true, 'context' => ['exported_version' => $version]]);

            return $path;
        } catch (\Throwable $e) {
            if ($opened) {
                $zip->close();
            }
            @unlink($path);
            throw $e;
        } finally {
            foreach ($temporaryAssets as $temporary) {
                @unlink($temporary);
            }
        }
    }

    private function flatten(array $data, string $prefix, array &$rows): void
    {
        foreach ($data as $key => $value) {
            $field = ltrim($prefix.'.'.$key, '.');
            if (is_array($value)) {
                $this->flatten($value, $field, $rows);
            } else {
                $text = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
                // Spreadsheet exports must treat user-supplied values as text, not formulas.
                if (preg_match('/^[\s]*[=+@-]/', $text)) {
                    $text = "'".$text;
                }
                $rows[] = [$field, $text];
            }
        }
    }
}
