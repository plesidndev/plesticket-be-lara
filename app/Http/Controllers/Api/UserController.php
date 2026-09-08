<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\CreateUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

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

    // SUPER_ADMIN — create another super admin. Regular members register themselves.
    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = $this->service->createAdmin($request->validated());

        return $this->created('Admin user created.', new UserResource($user));
    }

    public function show(string $uid): JsonResponse
    {
        try {
            $user = $this->service->findByUid($uid);
        } catch (RuntimeException $exception) {
            return $this->error($exception->getMessage(), 404);
        }

        return $this->success('User retrieved.', new UserResource($user));
    }

    public function update(UpdateUserRequest $request, string $uid): JsonResponse
    {
        try {
            $user = $this->service->update($uid, $request->validated());
        } catch (RuntimeException $exception) {
            return $this->error($exception->getMessage(), 404);
        }

        return $this->success('User updated.', new UserResource($user));
    }

    public function destroy(string $uid): JsonResponse
    {
        try {
            $this->service->delete($uid);
        } catch (RuntimeException $exception) {
            return $this->error($exception->getMessage(), 404);
        }

        return $this->success('User deleted.');
    }
}
