<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Talent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'type', 'category', 'genre', 'photo', 'bio', 'origin_city',
        'contact_name', 'contact_phone', 'contact_email',
        'instagram', 'tiktok', 'youtube', 'spotify',
        'is_verified', 'is_active', 'submitted_by', 'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'is_active'   => 'boolean',
        ];
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function eventTalents(): HasMany
    {
        return $this->hasMany(EventTalent::class);
    }
}
