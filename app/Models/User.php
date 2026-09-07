<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
