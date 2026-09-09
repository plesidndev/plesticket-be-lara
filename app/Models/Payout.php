<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payout extends Model
{
    use HasUuids;

    protected $fillable = [
        'reference', 'organizer_id', 'period_start', 'period_end',
        'gross_amount', 'platform_fee_amount', 'agent_commission_amount', 'net_amount',
        'status', 'bank_reference', 'note', 'approved_by', 'approved_at', 'paid_by', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'gross_amount' => 'decimal:2',
            'platform_fee_amount' => 'decimal:2',
            'agent_commission_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayoutLine::class);
    }
}
