<?php

namespace App\Models;

use App\Enums\IdentityType;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'event_id',
        'user_id',
        'title',
        'slug',
        'description',
        'category',
        'banner_url',
        'pic_name',
        'pic_identity_type',
        'pic_identity_number',
        'pic_npwp',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'is_online',
        'venue_name',
        'address',
        'city',
        'province',
        'verification_status',
        'rejection_reason',
        'verified_at',
        'verified_by',
        'show_status',
        'is_published',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'pic_identity_type'   => IdentityType::class,
            'pic_identity_number' => 'encrypted',
            'pic_npwp'            => 'encrypted',
            'verification_status' => VerificationStatus::class,
            'start_date'          => 'date',
            'end_date'            => 'date',
            'verified_at'         => 'datetime',
            'is_online'           => 'boolean',
            'show_status'         => 'boolean',
            'is_published'        => 'boolean',
            'latitude'            => 'decimal:7',
            'longitude'           => 'decimal:7',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class)->orderBy('price');
    }
}
