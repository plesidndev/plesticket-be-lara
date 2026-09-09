<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutLine extends Model
{
    protected $fillable = [
        'payout_id', 'order_id', 'order_number',
        'gross_amount', 'platform_fee_amount', 'agent_commission_amount', 'net_amount',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'platform_fee_amount' => 'decimal:2',
            'agent_commission_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
        ];
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
