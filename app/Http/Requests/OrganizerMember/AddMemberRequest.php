<?php

namespace App\Http\Requests\OrganizerMember;

use App\Enums\OrganizerRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Enum;

class AddMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'email'           => ['nullable', 'email', 'max:100'],
            'password'        => ['required', 'string', 'min:8'],
            'role'            => ['required', new Enum(OrganizerRole::class)],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
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
