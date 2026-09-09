<?php

namespace App\Services;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
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
        $user = $this->users->create([
            'name' => $data['name'],
            'username' => $data['username'] ?? null,
            'email' => strtolower(trim($data['email'])),
            'phone' => $data['phone'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'password' => $data['password'],
            'role' => isset($data['role']) ? UserRole::from($data['role']) : UserRole::Admin,
            'is_active' => $data['is_active'] ?? true,
        ]);

        // Seed the role's preset. From here the account's own grants are the only thing consulted,
        // so editing them later does not have to keep the role in step.
        $preset = $user->role->defaultPermissions();

        if ($preset !== []) {
            $user->permissions()->createMany(array_map(
                static fn (Permission $permission): array => ['permission' => $permission->value, 'granted_at' => now()],
                $preset,
            ));
        }

        return $user->load('permissions');
    }

    /** @param array<string, mixed> $filters */
    public function listOrganizers(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->users->paginateOrganizers($perPage, $filters);
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

    /**
     * Replace an account's grants with exactly the list given.
     *
     * A super admin holds no rows — it bypasses the check — so writing grants to one is refused
     * rather than silently stored, which would suggest they could be taken away again.
     *
     * @param  list<string>  $permissions
     * @return list<string>
     */
    public function syncPermissions(string $uid, array $permissions, User $grantedBy): array
    {
        $user = $this->findByUid($uid);

        if ($user->role === UserRole::SuperAdmin) {
            throw new \DomainException('A super admin already holds every permission.');
        }

        // Nobody edits their own grants. Blocking the whole operation rather than just the
        // removal of users.manage closes self-escalation too: an admin who can manage users would
        // otherwise be able to grant itself everything. A super admin is unaffected — it bypasses
        // the checks — so there is always someone able to fix a mistake.
        if ($user->is($grantedBy)) {
            throw new \DomainException('You cannot edit your own permissions.');
        }

        $wanted = array_values(array_unique($permissions));

        DB::transaction(function () use ($user, $wanted, $grantedBy): void {
            $user->permissions()->whereNotIn('permission', $wanted ?: ['__none__'])->delete();

            $existing = $user->permissions()->pluck('permission')->all();
            $rows = [];

            foreach (array_diff($wanted, $existing) as $permission) {
                $rows[] = ['permission' => $permission, 'granted_by' => $grantedBy->id, 'granted_at' => now()];
            }

            if ($rows !== []) {
                $user->permissions()->createMany($rows);
            }
        });

        return $user->load('permissions')->permissions->pluck('permission')->all();
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
