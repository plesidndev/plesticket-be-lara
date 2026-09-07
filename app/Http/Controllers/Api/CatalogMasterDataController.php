<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SaveCatalogMasterDataRequest;
use App\Http\Requests\Catalog\UploadCatalogDspLogoRequest;
use App\Http\Resources\CatalogMasterDataResource;
use App\Services\Catalog\CatalogMasterDataService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class CatalogMasterDataController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CatalogMasterDataService $masters) {}

    public function uploadDspLogo(UploadCatalogDspLogoRequest $request, string $code): JsonResponse
    {
        return $this->success('DSP logo updated.', new CatalogMasterDataResource($this->masters->uploadDspLogo($code, $request->file('logo'))));
    }

    public function index(string $masterType): JsonResponse
    {
        return $this->success('Catalog master data retrieved.', CatalogMasterDataResource::collection($this->masters->list($masterType)));
    }

    public function adminIndex(string $masterType): JsonResponse
    {
        return $this->success('Catalog master data retrieved.', CatalogMasterDataResource::collection($this->masters->list($masterType, false)));
    }

    public function store(SaveCatalogMasterDataRequest $request, string $masterType): JsonResponse
    {
        return $this->created('Catalog master entry created.', new CatalogMasterDataResource($this->masters->save($masterType, $request->validated())));
    }

    public function update(SaveCatalogMasterDataRequest $request, string $masterType, string $code): JsonResponse
    {
        return $this->success('Catalog master entry updated.', new CatalogMasterDataResource($this->masters->save($masterType, $request->validated(), $code)));
    }
}
