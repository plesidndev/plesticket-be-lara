<?php

namespace App\Models;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id',
        'reference_id',
        'provider',
        'method_code',
        'type',
        'status',
        'amount',
        'provider_reference',
        'provider_method_reference',
        'qr_string',
        'account_number',
        'checkout_url',
        'expires_at',
        'paid_at',
        'requires_refund',
        'provider_payload',
    ];

    protected $hidden = ['provider_payload'];

    protected function casts(): array
    {
        return [
            'provider'         => PaymentProvider::class,
            'type'             => PaymentType::class,
            'status'           => PaymentStatus::class,
            'amount'           => 'decimal:2',
            'requires_refund'  => 'boolean',
            'expires_at'       => 'datetime',
            'paid_at'          => 'datetime',
            'provider_payload' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }

    /** Still usable by the buyer: created, unpaid, and not past its deadline. */
    public function isActive(): bool
    {
        return $this->isPending() && ! $this->isExpired();
    }
}
