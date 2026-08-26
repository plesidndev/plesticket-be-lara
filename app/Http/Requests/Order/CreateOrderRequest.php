<?php

namespace App\Http\Requests\Order;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $enabledPaymentMethods = collect(config('payments.methods', []))
            ->where('enabled', true)
            ->pluck('code')
            ->all();

        return [
            'event_id'               => ['required', 'string'],
            'payment_method'         => ['sometimes', 'required', 'string', Rule::in($enabledPaymentMethods)],
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.ticket_type_id' => ['required', 'integer', 'exists:ticket_types,id'],
            'items.*.quantity'      => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'status'  => 'error',
            'message' => 'Validation failed.',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
