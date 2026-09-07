<?php

namespace App\Http\Controllers\Api;

use App\Enums\CatalogStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CatalogListRequest;
use App\Http\Requests\Catalog\CatalogUploadRequest;
use App\Http\Requests\Catalog\SaveCatalogRequest;
use App\Http\Resources\CatalogMasterDataResource;
use App\Http\Resources\CatalogReleaseResource;
use App\Services\Catalog\CatalogMasterDataService;
use App\Services\Catalog\CatalogService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CatalogService $catalog) {}

    public function options(CatalogMasterDataService $masters): JsonResponse
    {
        return $this->success('Catalog options retrieved.', [
            'types' => ['music', 'music_video'], 'release_types' => ['single', 'ep', 'album'],
            'statuses' => array_column(CatalogStatus::cases(), 'value'),
            'music_stores' => config('catalog.music_stores'), 'video_stores' => config('catalog.video_stores'),
            'genres' => CatalogMasterDataResource::collection($masters->list('genres')),
            'languages' => CatalogMasterDataResource::collection($masters->list('languages')),
            'ai_usage' => ['none', 'assisted', 'generated'], 'explicit' => ['no', 'yes', 'clean'],
            'uploads' => [
                'audio' => ['extensions' => ['wav', 'flac'], 'max_kb' => config('catalog.max_audio_kb')],
                'video' => ['extensions' => ['mp4', 'mov'], 'max_kb' => config('catalog.max_video_kb')],
                'artwork' => ['extensions' => ['jpg', 'jpeg', 'png'], 'max_kb' => config('catalog.max_artwork_kb'), 'min_pixels' => config('catalog.artwork_min_pixels'), 'square' => true],
            ],
        ]);
    }

    public function index(CatalogListRequest $request): JsonResponse
    {
        $page = $this->catalog->list(auth('api')->id(), $request->validated());

        return $this->paginated('Catalog retrieved.', CatalogReleaseResource::collection($page), $page);
    }

    public function store(SaveCatalogRequest $request): JsonResponse
    {
        return $this->created('Catalog draft created.', new CatalogReleaseResource($this->catalog->create(auth('api')->id(), $request->validated())));
    }

    public function show(string $release): JsonResponse
    {
        return $this->success('Catalog release retrieved.', new CatalogReleaseResource($this->catalog->show($release, auth('api')->id())));
    }

    public function update(SaveCatalogRequest $request, string $release): JsonResponse
    {
        return $this->success('Catalog draft updated.', new CatalogReleaseResource($this->catalog->update($release, auth('api')->id(), $request->validated())));
    }

    public function destroy(string $release): JsonResponse
    {
        $this->catalog->delete($release, auth('api')->id());

        return $this->success('Catalog draft deleted.');
    }

    public function upload(CatalogUploadRequest $request, string $release): JsonResponse
    {
        return $this->created('Catalog asset uploaded.', new CatalogReleaseResource($this->catalog->upload($release, auth('api')->id(), $request->file('file'), $request->safe()->except('file'))));
    }

    public function removeAsset(string $release, string $asset): JsonResponse
    {
        $this->catalog->removeAsset($release, auth('api')->id(), $asset);

        return $this->success('Catalog asset removed from the draft.');
    }

    public function download(string $release, string $asset): StreamedResponse
    {
        $file = $this->catalog->asset($release, auth('api')->id(), $asset);

        return Storage::disk($file->disk)->download($file->path, $file->original_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function submit(string $release): JsonResponse
    {
        return $this->success('Catalog submitted for review.', new CatalogReleaseResource($this->catalog->submit($release, auth('api')->id())));
    }

    public function requestChange(Request $request, string $release): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:5000']]);

        return $this->success('Change request sent to the review team.', new CatalogReleaseResource($this->catalog->requestChange($release, auth('api')->id(), $data['message'])));
    }

    public function submission(string $release, int $version): JsonResponse
    {
        $submission = $this->catalog->submission($release, auth('api')->id(), $version);

        return $this->success('Catalog submission retrieved.', ['version' => $submission->version, 'snapshot' => $submission->snapshot, 'created_at' => $submission->created_at]);
    }
}
