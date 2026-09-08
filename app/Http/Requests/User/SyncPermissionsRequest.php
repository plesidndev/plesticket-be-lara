<?php

namespace App\Http\Requests\User;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * The full set of grants the account should end up with. This replaces rather than merges, so a
 * permission left out of the list is revoked — the editor always submits the whole grid.
 */
class SyncPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => [Rule::in(Permission::codes())],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422));
    }
}
