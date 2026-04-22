<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'uid'           => $this->uid,
            'name'          => $this->name,
            'username'      => $this->username,
            'email'         => $this->email,
            'phone'         => $this->phone,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'photo'         => $this->photo ? asset('storage/' . $this->photo) : null,
            'role'          => $this->role->value,
            'role_label'    => $this->role->label(),
            'is_active'     => $this->is_active,
            'created_at'    => $this->created_at,
        ];
    }
}