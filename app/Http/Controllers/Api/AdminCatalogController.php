<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CatalogListRequest;
use App\Http\Requests\Catalog\CatalogTransitionRequest;
use App\Http\Resources\AdminCatalogReleaseResource;
use App\Services\Catalog\CatalogExportService;
use App\Services\Catalog\CatalogService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminCatalogController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CatalogService $catalog) {}

    public function index(CatalogListRequest $request): JsonResponse
    {
        $page = $this->catalog->list(null, $request->validated());

        return $this->paginated('Catalog review queue retrieved.', AdminCatalogReleaseResource::collection($page), $page);
    }

    public function show(string $release): JsonResponse
    {
        return $this->success('Catalog release retrieved.', new AdminCatalogReleaseResource($this->catalog->show($release, null)));
    }

    public function transition(CatalogTransitionRequest $request, string $release): JsonResponse
    {
        return $this->success('Catalog status updated.', new AdminCatalogReleaseResource($this->catalog->transition($release, auth('api')->id(), $request->validated())));
    }

    public function assign(Request $request, string $release): JsonResponse
    {
        $data = $request->validate(['assigned_admin_id' => ['present', 'nullable', 'integer', 'min:1']]);

        return $this->success('Review assignment updated.', new AdminCatalogReleaseResource($this->catalog->assign($release, auth('api')->id(), $data['assigned_admin_id'])));
    }

    public function note(Request $request, string $release): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:10000']]);

        return $this->success('Internal note added.', new AdminCatalogReleaseResource($this->catalog->note($release, auth('api')->id(), $data['message'])));
    }

    public function distribution(Request $request, string $release): JsonResponse
    {
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'provider' => ['required', 'string', 'max:100'],
            'reference' => ['required', 'string', 'max:255'],
            'url' => ['sometimes', 'nullable', 'url:https', 'max:2048'],
            'submitted_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'upc' => ['sometimes', 'nullable', 'regex:/\A(?:[0-9]{12}|[0-9]{13})\z/'],
            'video_isrc' => ['sometimes', 'nullable', 'regex:/\A[A-Z]{2}[A-Z0-9]{3}[0-9]{7}\z/'],
            'isrcs' => ['sometimes', 'array', 'max:100'],
            'isrcs.*' => ['array:track_id,isrc'],
            'isrcs.*.track_id' => ['required', 'uuid', 'distinct'],
            'isrcs.*.isrc' => ['required', 'regex:/\A[A-Z]{2}[A-Z0-9]{3}[0-9]{7}\z/'],
        ]);

        return $this->success('Internal distribution record saved.', new AdminCatalogReleaseResource($this->catalog->distribution($release, auth('api')->id(), $data)));
    }

    public function delivery(Request $request, string $release, string $store): JsonResponse
    {
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:pending,processing,scheduled,live,failed,removed'],
            'url' => ['required_if:status,live', 'nullable', 'url:https', 'max:2048'],
        ]);

        return $this->success('Store delivery updated.', new AdminCatalogReleaseResource($this->catalog->delivery($release, auth('api')->id(), $store, $data)));
    }

    public function download(string $release, string $asset): StreamedResponse
    {
        $file = $this->catalog->asset($release, null, $asset);

        return Storage::disk($file->disk)->download($file->path, $file->original_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function submission(string $release, int $version): JsonResponse
    {
        $submission = $this->catalog->submission($release, null, $version);

        return $this->success('Catalog submission retrieved.', ['version' => $submission->version, 'snapshot' => $submission->snapshot, 'created_at' => $submission->created_at]);
    }

    public function export(CatalogExportService $export, string $release, int $version): BinaryFileResponse
    {
        return response()->download($export->build($release, $version, auth('api')->id()), 'catalog-'.$release.'-v'.$version.'.zip', ['Cache-Control' => 'private, no-store'])->deleteFileAfterSend(true);
    }
}
