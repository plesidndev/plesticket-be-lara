<?php

namespace App\Repositories;

use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class UserRepository implements UserRepositoryInterface
{
    public function create(array $data): User
    {
        $user = User::create($data);

        $uid = match ($user->role) {
            UserRole::SuperAdmin => sprintf('SA%04d', $user->id),
            UserRole::Admin => sprintf('AD%04d', $user->id),
            default => sprintf('U%06d', $user->id),
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

    public function findByUid(string $uid): ?User
    {
        return User::where('uid', $uid)->first();
    }

    public function paginate(int $perPage, array $filters = []): LengthAwarePaginator
    {
        $query = User::query()->orderBy('id');

        // Accepts one role or a comma-separated list, so a console can pull SUPER_ADMIN and ADMIN
        // as a single "staff" directory with working totals instead of filtering client-side.
        if (! empty($filters['role'])) {
            $roles = array_values(array_filter(array_map('trim', explode(',', (string) $filters['role']))));

            $query->whereIn('role', $roles);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('email', 'like', '%'.$filters['search'].'%');
            });
        }

        return $query->paginate($perPage);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh();
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
