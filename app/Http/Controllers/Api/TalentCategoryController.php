<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Talent\SaveTalentCategoryRequest;
use App\Http\Resources\TalentCategoryResource;
use App\Services\TalentCategoryService;
use App\Services\AuditLogger;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class TalentCategoryController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TalentCategoryService $categories, private readonly AuditLogger $audit) {}

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
        $category = $this->categories->save($request->validated());
        $this->audit->record('talent_category.created', 'talent_category', (string) $category->code, $category->name);

        return $this->created('Talent category created.', new TalentCategoryResource($category));
    }

    public function update(SaveTalentCategoryRequest $request, string $code): JsonResponse
    {
        $category = $this->categories->save($request->validated(), $code);
        $this->audit->record('talent_category.updated', 'talent_category', (string) $code, $category->name, $request->validated());

        return $this->success('Talent category updated.', new TalentCategoryResource($category));
    }
}
