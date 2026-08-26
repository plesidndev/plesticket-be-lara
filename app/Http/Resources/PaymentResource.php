<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'reference_id' => $this->reference_id,
            'order_number' => $this->whenLoaded('order', fn() => $this->order->order_number),
            'method_code'  => $this->method_code,
            'type'         => $this->type->value,
            'provider'     => $this->provider->value,
            'status'       => $this->status->value,
            'status_label' => $this->status->label(),
            'amount'       => (float) $this->amount,
            'expires_at'   => $this->expires_at?->toISOString(),
            'paid_at'      => $this->paid_at?->toISOString(),
            'created_at'   => $this->created_at->toISOString(),

            // Only the field relevant to this method is populated.
            'instruction'  => array_filter([
                'qr_string'      => $this->qr_string,
                'account_number' => $this->account_number,
                'checkout_url'   => $this->checkout_url,
            ], fn($value) => $value !== null),
        ];
    }
}
