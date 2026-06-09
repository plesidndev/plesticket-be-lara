<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    use HasUuids;

    protected $fillable = [
        'ticket_code',
        'order_id',
        'order_item_id',
        'ticket_type_id',
        'event_id',
        'buyer_id',
        'holder_name',
        'status',
        'scanned_at',
        'scanned_by',
    ];

    protected function casts(): array
    {
        return [
            'status'     => TicketStatus::class,
            'scanned_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(OrganizerMember::class, 'scanned_by');
    }
}
