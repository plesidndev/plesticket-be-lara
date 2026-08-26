<?php

namespace App\Models;

use App\Enums\PaymentProvider;
use App\Enums\WebhookDeliveryStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * An audit record of one verified provider callback.
 *
 * Written before the callback is dispatched, so a payload that fails mid-flight
 * survives to be inspected and replayed. Only callbacks that pass signature
 * verification are stored — persisting rejected ones would let anyone fill the
 * table by posting to a public URL.
 */
class WebhookDelivery extends Model
{
    use HasUuids;

    protected $fillable = [
        'provider',
        'event_type',
        'reference_id',
        'status',
        'payload',
        'error',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'provider'     => PaymentProvider::class,
            'status'       => WebhookDeliveryStatus::class,
            'payload'      => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function needsAttention(): bool
    {
        return $this->status->needsAttention();
    }
}
