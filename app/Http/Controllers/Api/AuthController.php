<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\UserRole;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuthService $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->auth->register($request->validated(), UserRole::RegisteredUser);

        return $this->created('Registration successful.', [
            'token' => $result['token'],
            'user'  => new UserResource($result['user']),
        ]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->auth->login($request->email, $request->password);
        } catch (AuthenticationException $e) {
            return $this->error($e->getMessage(), 401);
        }

        return $this->success('Login successful.', [
            'token' => $result['token'],
            'user'  => new UserResource($result['user']),
        ]);
    }

    public function me(): JsonResponse
    {
        return $this->success('ok', new UserResource(auth('api')->user()));
    }

    public function logout(): JsonResponse
    {
        $this->auth->logout();

        return $this->success('Logged out successfully.');
    }
}