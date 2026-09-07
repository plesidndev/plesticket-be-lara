<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class SaveCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Metadata is validated against the persisted release type in the service.
        return [
            'type' => [$this->route('release') ? 'prohibited' : 'required', 'in:music,music_video'],
            'title' => [$this->route('release') ? 'sometimes' : 'required', 'string', 'max:255'],
            'metadata' => ['sometimes', 'array'],
            'status' => ['prohibited'],
            'user_id' => ['prohibited'],
            'distribution' => ['prohibited'],
            'assigned_admin_id' => ['prohibited'],
        ];
    }
}
