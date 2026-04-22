<?php

namespace App\Services;

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

    public function findByUid(string $uid): User
    {
        $user = $this->users->findByUid($uid);

        if (! $user) {
            throw new \RuntimeException('User not found.');
        }

        return $user;
    }

    public function update(string $uid, array $data): User
    {
        $user = $this->findByUid($uid);

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
