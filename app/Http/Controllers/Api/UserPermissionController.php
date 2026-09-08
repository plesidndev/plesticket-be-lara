<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\SyncPermissionsRequest;
use App\Services\UserService;
use App\Traits\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class UserPermissionController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly UserService $service) {}

    /**
     * The account's grants alongside the full catalog, so an editor can render the grid without
     * hardcoding a permission list that would drift from the enum.
     */
    public function show(string $uid): JsonResponse
    {
        try {
            $user = $this->service->findByUid($uid);
        } catch (RuntimeException $exception) {
            return $this->error($exception->getMessage(), 404);
        }

        return $this->success('Permissions retrieved.', [
            'uid' => $user->uid,
            'role' => $user->role->value,
            'bypasses_checks' => $user->role === \App\Enums\UserRole::SuperAdmin,
            'granted' => $user->permissions()->pluck('permission')->all(),
            'available' => array_map(static fn (Permission $case): array => [
                'code' => $case->value,
                'group' => $case->group(),
                'label' => $case->label(),
            ], Permission::cases()),
        ]);
    }

    public function update(SyncPermissionsRequest $request, string $uid): JsonResponse
    {
        try {
            $granted = $this->service->syncPermissions($uid, $request->validated('permissions'), $request->user());
        } catch (RuntimeException $exception) {
            return $this->error($exception->getMessage(), 404);
        } catch (DomainException $exception) {
            // Mirrored under errors.permissions as well as the message, so a client can show the
            // refusal against the field it belongs to rather than as a bare banner.
            return $this->error($exception->getMessage(), 422, ['permissions' => [$exception->getMessage()]]);
        }

        return $this->success('Permissions updated.', ['uid' => $uid, 'granted' => $granted]);
    }
}
