<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function list(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->users->paginate($perPage, $filters);
    }

    /**
     * Create a staff account from the console. Mirrors AuthService::register but issues no token,
     * since the acting super admin is creating the account on someone else's behalf. The role is
     * narrowed to the two staff roles by CreateUserRequest, and defaults to the lesser one;
     * regular members sign themselves up via /auth/register.
     */
    public function createAdmin(array $data): User
    {
        return $this->users->create([
            'name' => $data['name'],
            'username' => $data['username'] ?? null,
            'email' => strtolower(trim($data['email'])),
            'phone' => $data['phone'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'password' => $data['password'],
            'role' => isset($data['role']) ? UserRole::from($data['role']) : UserRole::Admin,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function findByUid(string $uid): User
    {
        $user = $this->users->findByUid($uid);

        if (! $user) {
            throw new \RuntimeException('User not found.');
        }

        return $user;
    }

    /**
     * Apply a partial update. Only the keys present are written, so callers can send a single field.
     * Email is normalised the same way registration does it; the password cast handles hashing.
     */
    public function update(string $uid, array $data): User
    {
        $user = $this->findByUid($uid);

        if (isset($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
        }

        return $this->users->update($user, $data);
    }

    public function uploadPhoto(User $user, UploadedFile $file): User
    {
        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }

        $path = $file->store('photos', 'public');

        return $this->users->update($user, ['photo' => $path]);
    }

    public function delete(string $uid): void
    {
        $user = $this->findByUid($uid);

        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }

        $this->users->delete($user);
    }
}
