<?php

namespace App\Http\Requests\TicketType;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateTicketTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Every field is optional so a caller can change one of them.
     *
     * Two rules differ from creation on purpose. `quota` allows 0 because it counts what is left
     * rather than what the tier started with, so closing a tier's remaining stock is a legitimate
     * edit while creating an empty tier is not. The sale window carries no cross-field rule here:
     * a request may hold one half of the pair, so TicketTypeService judges it against the stored
     * half instead.
     */
    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price'       => ['sometimes', 'numeric', 'min:0'],
            'quota'       => ['sometimes', 'integer', 'min:0'],
            'is_active'   => ['sometimes', 'boolean'],
            'sale_start'  => ['sometimes', 'nullable', 'date'],
            'sale_end'    => ['sometimes', 'nullable', 'date'],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'status'  => 'error',
            'message' => 'Validation failed',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
