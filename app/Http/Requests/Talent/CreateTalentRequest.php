<?php

namespace App\Http\Requests\Talent;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateTalentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:150'],
            'slug'          => ['nullable', 'string', 'max:180'],
            'type'          => ['required', 'in:personal,group'],
            'category'      => ['required', 'in:music,band,dj,comedian,speaker,mc,dancer,other'],
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
