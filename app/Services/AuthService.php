<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Auth\AuthenticationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function register(array $data, UserRole $role = UserRole::RegisteredUser): array
    {
        $user = $this->users->create([
            'name'          => $data['name'],
            'username'      => $data['username'] ?? null,
            'email'         => strtolower(trim($data['email'])),
            'phone'         => $data['phone'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'password'      => $data['password'],
            'role'          => $role,
            'is_active'     => true,
        ]);

        $token = JWTAuth::fromUser($user);

        return ['token' => $token, 'user' => $user];
    }

    public function login(string $email, string $password): array
    {
        $token = auth('api')->attempt([
            'email'    => strtolower(trim($email)),
            'password' => $password,
        ]);

        if (! $token) {
            throw new AuthenticationException('Invalid email or password.');
        }

        $user = auth('api')->user();

        if (! $user->is_active) {
            auth('api')->logout();
            throw new AuthenticationException('Account is inactive.');
        }

        return ['token' => $token, 'user' => $user];
    }

    public function logout(): void
    {
        auth('api')->logout();
    }
}