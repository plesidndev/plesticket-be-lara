<?php

namespace App\Http\Requests\Talent;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateTalentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'          => ['sometimes', 'string', 'max:150'],
            'slug'          => ['sometimes', 'nullable', 'string', 'max:180'],
            'type'          => ['sometimes', 'in:personal,group'],
            'category'      => ['sometimes', 'string', Rule::exists('talent_categories', 'code')->where('is_active', true)],
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
            'single_url'    => ['nullable', 'url', 'max:200'],
            'youtube_videos' => ['nullable', 'array', 'max:10'],
            // Held to YouTube because the field is presented as the act's YouTube box; youtu.be
            // and the m./www. hosts are the same links people paste from a phone or a browser.
            'youtube_videos.*' => ['required', 'url', 'max:200', 'regex:/^https:\/\/(www\.|m\.)?(youtube\.com|youtu\.be)\//i'],
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
