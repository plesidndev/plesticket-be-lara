<?php

namespace App\Http\Requests\Event;

use App\Enums\IdentityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Enum;

class CreateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Basic info
            'title'       => ['required', 'string', 'max:255'],
            'slug'        => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['nullable', 'string'],
            'category'    => ['nullable', 'string', 'max:100'],
            'banner_url'  => ['nullable', 'url'],

            // PIC
            'pic_name'            => ['required', 'string', 'max:255'],
            'pic_identity_type'   => ['required', new Enum(IdentityType::class)],
            'pic_identity_number' => ['required', 'string', 'max:50'],
            'pic_npwp'            => ['nullable', 'string', 'max:30'],

            // Schedule
            'start_date'  => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'end_date'    => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'start_time'  => ['nullable', 'date_format:H:i'],
            'end_time'    => ['nullable', 'date_format:H:i'],

            // Location
            'is_online'   => ['sometimes', 'boolean'],
            'venue_name'  => ['nullable', 'string', 'max:255'],
            'address'     => ['nullable', 'string'],
            'city'        => ['nullable', 'string', 'max:100'],
            'province'    => ['nullable', 'string', 'max:100'],

            // Visibility
            'show_status' => ['sometimes', 'boolean'],
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
