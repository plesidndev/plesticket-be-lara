<?php

namespace App\Http\Requests\Talent;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SaveTalentCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $updating = $this->route('code') !== null;

        return [
            'code' => [$updating ? 'prohibited' : 'required', 'string', 'max:40', 'regex:/\A[a-z][a-z0-9]*(?:[-_][a-z0-9]+)*\z/'],
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'between:0,100000'],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
