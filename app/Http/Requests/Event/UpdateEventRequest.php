<?php

namespace App\Http\Requests\Event;

use App\Enums\IdentityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Enum;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'       => ['sometimes', 'string', 'max:255'],
            'slug'        => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['sometimes', 'nullable', 'string'],
            'category'    => ['sometimes', 'nullable', 'string', 'max:100'],
            'banner'      => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'banner_url'  => ['sometimes', 'nullable', 'url'],

            'pic_name'            => ['sometimes', 'string', 'max:255'],
            'pic_identity_type'   => ['sometimes', new Enum(IdentityType::class)],
            'pic_identity_number' => ['sometimes', 'string', 'max:50'],
            'pic_npwp'            => ['sometimes', 'nullable', 'string', 'max:30'],

            'start_date'  => ['sometimes', 'date', 'date_format:Y-m-d'],
            'end_date'    => ['sometimes', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'start_time'  => ['sometimes', 'nullable', 'date_format:H:i'],
            'end_time'    => ['sometimes', 'nullable', 'date_format:H:i'],

            'is_online'   => ['sometimes', 'boolean'],
            'venue_name'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'address'     => ['sometimes', 'nullable', 'string'],
            'city'        => ['sometimes', 'nullable', 'string', 'max:100'],
            'province'    => ['sometimes', 'nullable', 'string', 'max:100'],
            'latitude'    => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude'   => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],

            'show_status' => ['sometimes', 'boolean'],

            // Ticket types — if provided, replaces all existing
            'ticket_types'               => ['sometimes', 'array'],
            'ticket_types.*.name'        => ['required', 'string', 'max:100'],
            'ticket_types.*.description' => ['nullable', 'string'],
            'ticket_types.*.price'       => ['required', 'numeric', 'min:0'],
            'ticket_types.*.quota'       => ['required', 'integer', 'min:1'],
            'ticket_types.*.is_active'   => ['sometimes', 'boolean'],
            'ticket_types.*.sale_start'  => ['nullable', 'date'],
            'ticket_types.*.sale_end'    => ['nullable', 'date', 'after_or_equal:ticket_types.*.sale_start'],
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
