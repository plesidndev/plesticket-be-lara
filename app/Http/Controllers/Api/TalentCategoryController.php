<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Talent\SaveTalentCategoryRequest;
use App\Http\Resources\TalentCategoryResource;
use App\Services\TalentCategoryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class TalentCategoryController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TalentCategoryService $categories) {}

    public function index(): JsonResponse
    {
        return $this->success('Talent categories retrieved.', TalentCategoryResource::collection($this->categories->list()));
    }

    public function adminIndex(): JsonResponse
    {
        return $this->success('Talent categories retrieved.', TalentCategoryResource::collection($this->categories->list(false)));
    }

    public function store(SaveTalentCategoryRequest $request): JsonResponse
    {
        return $this->created('Talent category created.', new TalentCategoryResource($this->categories->save($request->validated())));
    }

    public function update(SaveTalentCategoryRequest $request, string $code): JsonResponse
    {
        return $this->success('Talent category updated.', new TalentCategoryResource($this->categories->save($request->validated(), $code)));
    }
}
