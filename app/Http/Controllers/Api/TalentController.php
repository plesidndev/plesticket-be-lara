<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Talent\CreateTalentRequest;
use App\Http\Requests\Talent\UpdateTalentRequest;
use App\Http\Resources\TalentResource;
use App\Services\TalentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class TalentController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TalentService $service) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list(
            (int) $request->query('limit', 20),
            $request->only(['search', 'type', 'category'])
        );

        return $this->paginated('Talents retrieved.', TalentResource::collection($paginator), $paginator);
    }

    public function mine(Request $request): JsonResponse
    {
        $paginator = $this->service->mine(
            auth('api')->id(),
            (int) $request->query('limit', 20),
            $request->only(['search'])
        );

        return $this->paginated('My talents retrieved.', TalentResource::collection($paginator), $paginator);
    }

    public function store(CreateTalentRequest $request): JsonResponse
    {
        $talent = $this->service->create(auth('api')->id(), $request->validated());

        return $this->created('Talent created.', new TalentResource($talent));
    }

    public function show(int $id): JsonResponse
    {
        try {
            $talent = $this->service->findOrFail($id);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }

        return $this->success('Talent retrieved.', new TalentResource($talent));
    }

    public function update(UpdateTalentRequest $request, int $id): JsonResponse
    {
        $isAdmin = auth('api')->user()?->role->value === 'SUPER_ADMIN';

        try {
            $talent = $this->service->update($id, auth('api')->id(), $isAdmin, $request->validated());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 403);
        }

        return $this->success('Talent updated.', new TalentResource($talent));
    }

    public function destroy(int $id): JsonResponse
    {
        $isAdmin = auth('api')->user()?->role->value === 'SUPER_ADMIN';

        try {
            $this->service->delete($id, auth('api')->id(), $isAdmin);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 403);
        }

        return $this->success('Talent deleted.');
    }

    // Super Admin only
    public function adminIndex(Request $request): JsonResponse
    {
        $filters = $request->only(['search']);
        if ($request->has('is_verified')) {
            $filters['is_verified'] = filter_var($request->query('is_verified'), FILTER_VALIDATE_BOOLEAN);
        }

        $paginator = $this->service->adminList(
            (int) $request->query('limit', 20),
            $filters
        );

        return $this->paginated('Talents retrieved.', TalentResource::collection($paginator), $paginator);
    }

    public function verify(int $id): JsonResponse
    {
        try {
            $talent = $this->service->verify($id, auth('api')->id());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success('Talent verified.', new TalentResource($talent));
    }
}
