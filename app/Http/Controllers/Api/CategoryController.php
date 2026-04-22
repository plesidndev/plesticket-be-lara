<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\CreateCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Services\CategoryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class CategoryController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CategoryService $service) {}

    // Public — active categories for dropdowns
    public function index(): JsonResponse
    {
        return $this->success('Categories retrieved.', CategoryResource::collection($this->service->listActive()));
    }

    // SUPER_ADMIN — paginated list with filters
    public function adminIndex(Request $request): JsonResponse
    {
        $filters   = $request->only(['search', 'is_active']);
        $paginator = $this->service->listAdmin((int) $request->query('limit', 15), $filters);

        return $this->paginated('Categories retrieved.', CategoryResource::collection($paginator), $paginator);
    }

    // SUPER_ADMIN — create
    public function store(CreateCategoryRequest $request): JsonResponse
    {
        try {
            $category = $this->service->create($request->validated());
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->created('Category created.', new CategoryResource($category));
    }

    // SUPER_ADMIN — update
    public function update(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        try {
            $category = $this->service->update($id, $request->validated());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success('Category updated.', new CategoryResource($category));
    }

    // SUPER_ADMIN — delete
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->delete($id);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }

        return $this->success('Category deleted.');
    }
}
