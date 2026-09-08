<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Enums\UserRole;
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

        // Without users.view_staff the directory is members only. Pinned here rather than trusted
        // from the query, so a hand-written role= cannot widen it back to the staff accounts.
        if (! $this->canSeeStaff($request)) {
            $filters['role'] = UserRole::RegisteredUser->value;
        }

        $paginator = $this->service->list((int) $request->query('limit', 15), $filters);

        return $this->paginated('Users retrieved.', UserResource::collection($paginator), $paginator);
    }

    // Needs users.manage. Regular members register themselves at /auth/register.
    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = $this->service->createAdmin($request->validated());

        return $this->created('Admin user created.', new UserResource($user));
    }

    public function show(Request $request, string $uid): JsonResponse
    {
        try {
            $user = $this->service->findByUid($uid);
        } catch (RuntimeException $exception) {
            return $this->error($exception->getMessage(), 404);
        }

        // Staff accounts are invisible without users.view_staff, matching the directory above.
        // 404 rather than 403: a 403 would confirm the account exists.
        if ($user->role->isStaff() && ! $this->canSeeStaff($request)) {
            return $this->error('User not found.', 404);
        }

        return $this->success('User retrieved.', new UserResource($user));
    }

    private function canSeeStaff(Request $request): bool
    {
        return $request->user()?->hasPermission(Permission::UsersViewStaff) === true;
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
