<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;
    public function __construct(private readonly UserService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['role', 'is_active', 'search']);

        if (isset($filters['is_active'])) {
            $filters['is_active'] = filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        $paginator = $this->service->list((int) $request->query('limit', 15), $filters);

        return $this->paginated('Users retrieved.', UserResource::collection($paginator), $paginator);
    }

    public function show(string $uid): JsonResponse
    {
        $user = $this->service->findByUid($uid);

        return $this->success('User retrieved.', new UserResource($user));
    }

    public function update(UpdateUserRequest $request, string $uid): JsonResponse
    {
        $user = $this->service->update($uid, $request->validated());

        return $this->success('User updated.', new UserResource($user));
    }

    public function destroy(string $uid): JsonResponse
    {
        $this->service->delete($uid);

        return $this->success('User deleted.');
    }
}