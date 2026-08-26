<?php

namespace App\Http\Resources;

use App\Services\Payments\Data\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PaymentMethod $resource
 */
class PaymentMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $method = $this->resource;

        return [
            'code'        => $method->code,
            'name'        => $method->name,
            'description' => $method->description,
            'type'        => $method->type->value,
            'provider'    => $method->provider->value,
            'logo_url'    => $method->logoUrl ? asset(ltrim($method->logoUrl, '/')) : null,
            'min_amount'  => $method->minAmount,
            'max_amount'  => $method->maxAmount,
            'fee'         => [
                'flat'    => $method->feeFlat,
                'percent' => $method->feePercent,
            ],
        ];
    }
}
