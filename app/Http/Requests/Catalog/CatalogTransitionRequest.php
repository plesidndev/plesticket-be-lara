<?php

namespace App\Http\Requests\Catalog;

use App\Enums\CatalogStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(CatalogStatus::class)],
            'version' => ['required', 'integer', 'min:1'],
            'message' => ['required_if:status,changes_requested,rejected', 'nullable', 'string', 'max:5000'],
            'fields' => ['sometimes', 'array', 'max:100'],
            'fields.*' => ['array:field,message'],
            'fields.*.field' => ['required', 'string', 'max:255'],
            'fields.*.message' => ['required', 'string', 'max:2000'],
        ];
    }
}
