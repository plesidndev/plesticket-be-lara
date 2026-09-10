<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'organizer_uid' => $this->whenLoaded('organizer', fn () => $this->organizer?->uid),
            'organizer_name' => $this->whenLoaded('organizer', fn () => $this->organizer?->name),
            'period_start' => $this->period_start->toDateString(),
            'period_end' => $this->period_end->toDateString(),
            'gross_amount' => (float) $this->gross_amount,
            'platform_fee_amount' => (float) $this->platform_fee_amount,
            'agent_commission_amount' => (float) $this->agent_commission_amount,
            'net_amount' => (float) $this->net_amount,
            'status' => $this->status->value,
            'source' => $this->source,
            'requested_at' => $this->requested_at?->toISOString(),
            'bank_name' => $this->bank_name,
            'account_number' => $this->account_number,
            'account_holder' => $this->account_holder,
            'bank_reference' => $this->bank_reference,
            'note' => $this->note,
            'approved_at' => $this->approved_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'order_number' => $line->order_number,
                'gross_amount' => (float) $line->gross_amount,
                'platform_fee_amount' => (float) $line->platform_fee_amount,
                'agent_commission_amount' => (float) $line->agent_commission_amount,
                'net_amount' => (float) $line->net_amount,
            ])),
        ];
    }
}
