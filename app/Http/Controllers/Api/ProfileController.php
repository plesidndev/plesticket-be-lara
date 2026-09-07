<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Requests\User\UploadPhotoRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly UserService $service) {}

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();
        $user->fill($request->safe()->except('photo'));
        if ($request->hasFile('photo')) {
            $user = $this->service->uploadPhoto($user, $request->file('photo'));
        }
        $user->profile_completed_at ??= now();
        $user->save();

        return $this->success('Profile saved.', new UserResource($user));
    }

    public function uploadPhoto(UploadPhotoRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();

        $user = $this->service->uploadPhoto($user, $request->file('photo'));

        return $this->success('Photo uploaded.', new UserResource($user));
    }
}
