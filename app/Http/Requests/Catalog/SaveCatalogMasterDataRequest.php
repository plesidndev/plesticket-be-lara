<?php

namespace App\Http\Requests\Catalog;

use App\Services\Catalog\CatalogTerritoryCodes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCatalogMasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $updating = $this->route('code') !== null;
        $codeRule = match ($this->route('masterType')) {
            'territories' => Rule::in(array_keys(CatalogTerritoryCodes::entries())),
            'timezones' => Rule::in(\DateTimeZone::listIdentifiers(\DateTimeZone::ALL)),
            default => 'regex:/\A[a-z][a-z0-9]*(?:[-_][a-z0-9]+)*\z/',
        };

        return [
            'code' => [$updating ? 'prohibited' : 'required', 'string', 'max:40', $codeRule],
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'between:0,100000'],
        ];
    }
}
