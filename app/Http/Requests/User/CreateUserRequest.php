<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Console account creation. Only the two staff roles may be requested, and the caller is already
 * a SUPER_ADMIN by the time this runs; regular members sign themselves up through /auth/register.
 * Omitting the role yields an ADMIN, the lesser of the two.
 */
class CreateUserRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'unique:users,email'],
            'password'      => ['required', 'string', 'min:8', 'confirmed'],
            'username'      => ['nullable', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'phone'         => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date', 'date_format:Y-m-d', 'before:today'],
            'role'          => ['sometimes', Rule::in([UserRole::SuperAdmin->value, UserRole::Admin->value])],
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
