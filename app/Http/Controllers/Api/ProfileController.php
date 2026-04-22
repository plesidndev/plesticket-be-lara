<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UploadPhotoRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly UserService $service) {}

    public function uploadPhoto(UploadPhotoRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth('api')->user();

        $user = $this->service->uploadPhoto($user, $request->file('photo'));

        return $this->success('Photo uploaded.', new UserResource($user));
    }
}
