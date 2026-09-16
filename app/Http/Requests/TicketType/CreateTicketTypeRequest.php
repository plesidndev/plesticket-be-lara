<?php

namespace App\Http\Requests\TicketType;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateTicketTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The same rules CreateEventRequest applies to each element of its ticket_types array, keyed
     * flat because this endpoint takes one tier. A client can map `quota` onto a form field
     * directly instead of unpicking `ticket_types.0.quota`.
     */
    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price'       => ['required', 'numeric', 'min:0'],
            'quota'       => ['required', 'integer', 'min:1'],
            'is_active'   => ['sometimes', 'boolean'],
            'sale_start'  => ['sometimes', 'nullable', 'date'],
            'sale_end'    => ['sometimes', 'nullable', 'date', 'after_or_equal:sale_start'],
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
