<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventTalent extends Model
{
    protected $table = 'event_talents';

    protected $fillable = [
        'event_id', 'talent_id', 'free_name', 'role', 'performance_order', 'performance_time',
    ];

    public function talent(): BelongsTo
    {
        return $this->belongsTo(Talent::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->talent?->name ?? $this->free_name ?? '—';
    }
}
