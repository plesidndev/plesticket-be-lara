<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'uid',
        'name',
        'username',
        'email',
        'email_verified_at',
        'phone',
        'date_of_birth',
        'photo',
        'password',
        'role',
        'is_plesconnect_user',
        'is_organizer',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Mirrors the column default so a freshly created instance reads `false`
     * rather than null before it is re-read from the database.
     */
    protected $attributes = [
        'is_plesconnect_user' => false,
        'is_organizer' => false,
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'is_plesconnect_user' => 'boolean',
            'is_organizer' => 'boolean',
            'date_of_birth' => 'date',
            'email_verified_at' => 'datetime',
            'profile_completed_at' => 'datetime',
        ];
    }

    /** @var list<string>|null Memoized for the life of the request; see permissionCodes(). */
    private ?array $permissionCodes = null;

    public function permissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    /**
     * The grants held by this account.
     *
     * Read from the database rather than the JWT: a token carries whatever was true when it was
     * issued, so a revoked permission would keep working until it expired. Memoized per instance
     * so a request that checks several permissions still costs one query.
     *
     * @return list<string>
     */
    public function permissionCodes(): array
    {
        return $this->permissionCodes ??= $this->relationLoaded('permissions')
            ? $this->permissions->pluck('permission')->all()
            : $this->permissions()->pluck('permission')->all();
    }

    /**
     * A super admin passes every check without consulting a row. Hardcoded on purpose: if the
     * role were just another bundle of grants, revoking the wrong one would lock everybody out
     * of user management with no way back in through the API.
     */
    public function hasPermission(Permission|string $permission): bool
    {
        if ($this->role === UserRole::SuperAdmin) {
            return true;
        }

        // Admin permissions only mean anything on a staff account. Without this a member who was
        // granted a row — by mistake, or by a stale grant left behind after a demotion — would
        // reach the console, and demoting somebody would silently fail to revoke their access.
        if (! $this->role->isStaff()) {
            return false;
        }

        $code = $permission instanceof Permission ? $permission->value : $permission;

        return in_array($code, $this->permissionCodes(), true);
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'uid' => $this->uid,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'is_plesconnect_user' => (bool) $this->is_plesconnect_user,
            // Lets the EO frontend gate without a round trip. Stale by nature:
            // activation issues a fresh token so the claim keeps up.
            'is_organizer' => (bool) $this->is_organizer,
        ];
    }
}
