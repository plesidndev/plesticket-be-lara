<?php

namespace App\Http\Requests\Talent;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateTalentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'          => ['sometimes', 'string', 'max:150'],
            'slug'          => ['sometimes', 'nullable', 'string', 'max:180'],
            'type'          => ['sometimes', 'in:personal,group'],
            'category'      => ['sometimes', 'in:music,band,dj,comedian,speaker,mc,dancer,other'],
            'genre'         => ['nullable', 'string', 'max:100'],
            'bio'           => ['nullable', 'string', 'max:2000'],
            'origin_city'   => ['nullable', 'string', 'max:100'],
            'contact_name'  => ['nullable', 'string', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'instagram'     => ['nullable', 'string', 'max:100'],
            'tiktok'        => ['nullable', 'string', 'max:100'],
            'youtube'       => ['nullable', 'string', 'max:200'],
            'spotify'       => ['nullable', 'string', 'max:200'],
            'is_active'     => ['sometimes', 'boolean'],
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
