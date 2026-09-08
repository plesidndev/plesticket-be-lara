<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Every field is `sometimes`, so a caller sending only the keys it means to change leaves the
     * rest untouched. Uniqueness ignores the account being edited, matched on its uid.
     */
    public function rules(): array
    {
        $uid = (string) $this->route('uid');

        return [
            'name'          => ['sometimes', 'string', 'max:255'],
            'username'      => ['sometimes', 'nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($uid, 'uid')],
            'email'         => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($uid, 'uid')],
            'phone'         => ['sometimes', 'nullable', 'string', 'max:20'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d', 'before:today'],
            'password'      => ['sometimes', 'string', 'min:8', 'confirmed'],
            'role'          => ['sometimes', new Enum(UserRole::class)],
            'is_active'     => ['sometimes', 'boolean'],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'status'  => 'error',
            'message' => 'Validation failed',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
