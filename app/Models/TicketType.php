<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketType extends Model
{
    protected $fillable = [
        'event_id',
        'name',
        'description',
        'price',
        'quota',
        'is_active',
        'sale_start',
        'sale_end',
    ];

    protected function casts(): array
    {
        return [
            'price'      => 'decimal:2',
            'quota'      => 'integer',
            'is_active'  => 'boolean',
            'sale_start' => 'datetime',
            'sale_end'   => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
