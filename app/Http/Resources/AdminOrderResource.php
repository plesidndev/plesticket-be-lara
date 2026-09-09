<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A transaction as the admin console sees it: buyer and event context on every row, and the
 * payment/ticket detail only when those relations were loaded for a single-order view.
 */
class AdminOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'total_price' => (float) $this->total_price,
            'payment_method' => $this->payment_method,
            'is_agent_sale' => (bool) $this->is_agent_sale,
            'buyer_name' => $this->buyer_name,
            'buyer_phone' => $this->buyer_phone,
            'buyer_email' => $this->whenLoaded('buyer', fn () => $this->buyer?->email),
            'buyer_uid' => $this->whenLoaded('buyer', fn () => $this->buyer?->uid),
            'event_id' => $this->event_id,
            'event_title' => $this->whenLoaded('event', fn () => $this->event?->title),
            'item_count' => $this->when($this->items_count !== null, fn () => (int) $this->items_count),
            'requires_refund' => $this->whenLoaded('payments', fn () => $this->payments->contains('requires_refund', true)),
            'paid_at' => $this->paid_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'agent_name' => $this->whenLoaded('agent', fn () => $this->agent?->name),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($payment) => [
                'reference_id' => $payment->reference_id,
                'provider' => $payment->provider,
                'method_code' => $payment->method_code,
                'status' => $payment->status->value,
                'amount' => (float) $payment->amount,
                'requires_refund' => (bool) $payment->requires_refund,
                'provider_reference' => $payment->provider_reference,
                'paid_at' => $payment->paid_at?->toISOString(),
                'created_at' => $payment->created_at->toISOString(),
            ])),
            'callbacks' => $this->whenLoaded('webhookDeliveries', fn () => $this->webhookDeliveries->map(fn ($delivery) => [
                'id' => $delivery->id,
                'provider' => $delivery->provider->value,
                'event_type' => $delivery->event_type,
                'reference_id' => $delivery->reference_id,
                'status' => $delivery->status->value,
                'error' => $delivery->error,
                'processed_at' => $delivery->processed_at?->toISOString(),
                'created_at' => $delivery->created_at->toISOString(),
            ])),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'ticket_type_name' => $item->ticket_type_name,
                'unit_price' => (float) $item->unit_price,
                'quantity' => $item->quantity,
                'subtotal' => (float) $item->subtotal,
                'tickets' => $item->relationLoaded('tickets') ? $item->tickets->map(fn ($ticket) => [
                    'ticket_code' => $ticket->ticket_code,
                    'holder_name' => $ticket->holder_name,
                    'status' => $ticket->status->value,
                    'scanned_at' => $ticket->scanned_at?->toISOString(),
                ]) : [],
            ])),
        ];
    }
}
