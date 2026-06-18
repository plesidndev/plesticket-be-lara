<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use App\Models\OrganizerMember;

class Order extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_number',
        'buyer_id',
        'agent_id',
        'is_agent_sale',
        'buyer_name',
        'buyer_phone',
        'event_id',
        'status',
        'total_price',
        'payment_method',
        'paid_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status'        => OrderStatus::class,
            'total_price'   => 'decimal:2',
            'paid_at'       => 'datetime',
            'expires_at'    => 'datetime',
            'is_agent_sale' => 'boolean',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(OrganizerMember::class, 'agent_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function tickets(): HasManyThrough
    {
        return $this->hasManyThrough(Ticket::class, OrderItem::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
