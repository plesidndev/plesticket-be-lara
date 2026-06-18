<?php

namespace App\Models;

use App\Enums\OrganizerRole;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tymon\JWTAuth\Contracts\JWTSubject;

class OrganizerMember extends Authenticatable implements JWTSubject
{
    protected $fillable = [
        'uid',
        'owner_id',
        'event_id',
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'commission_rate',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password'        => 'hashed',
            'role'            => OrganizerRole::class,
            'is_active'       => 'boolean',
            'commission_rate' => 'decimal:2',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'uid'             => $this->uid,
            'name'            => $this->name,
            'role'            => $this->role->value,
            'event_id'        => $this->event_id,
            'commission_rate' => (float) $this->commission_rate,
            'guard'           => 'organizer',
        ];
    }
}
