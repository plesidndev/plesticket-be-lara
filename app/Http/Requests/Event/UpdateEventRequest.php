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
