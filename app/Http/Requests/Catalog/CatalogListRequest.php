<?php

namespace App\Http\Requests\Catalog;

use App\Enums\CatalogStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'in:music,music_video'],
            'status' => ['sometimes', Rule::enum(CatalogStatus::class)],
            'search' => ['sometimes', 'string', 'max:255'],
            'has_change_request' => ['sometimes', 'boolean'],
            'assigned_admin_id' => [$this->is('api/admin/*') ? 'sometimes' : 'prohibited', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'between:1,100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
