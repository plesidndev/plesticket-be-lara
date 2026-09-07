<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class SaveCatalogMasterDataRequest extends FormRequest
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
}
