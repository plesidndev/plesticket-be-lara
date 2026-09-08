<?php

namespace App\Http\Resources;

use App\Enums\Permission;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uid' => $this->uid,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'photo' => $this->photo ? asset('storage/'.$this->photo) : null,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'has_completed_profile' => $this->profile_completed_at !== null,
            'is_plesconnect_user' => (bool) $this->is_plesconnect_user,
            'is_organizer' => (bool) $this->is_organizer,
            'is_active' => $this->is_active,
            // Read fresh from the database on every call rather than carried in the JWT, so a
            // revoked grant takes effect immediately instead of when the token expires. A super
            // admin holds no rows but bypasses every check, so it reports the full catalog —
            // consumers can then gate on this list alone without special-casing the role.
            'permissions' => $this->when(
                $this->role->isStaff(),
                fn (): array => $this->role === UserRole::SuperAdmin ? Permission::codes() : $this->permissionCodes(),
            ),
            'bypasses_permission_checks' => $this->role === UserRole::SuperAdmin,
            'created_at' => $this->created_at,
        ];
    }
}
