<?php

namespace App\Repositories;

use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    public function create(array $data): User
    {
        $user = User::create($data);

        $uid = match ($user->role) {
            UserRole::SuperAdmin     => sprintf('SA%04d', $user->id),
            UserRole::EventOrganizer => sprintf('EO%04d', $user->id),
            default                  => sprintf('U%06d', $user->id),
        };

        $user->update(['uid' => $uid]);

        return $user->fresh();
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function findById(int $id): ?User
    {
        return User::find($id);
    }
}