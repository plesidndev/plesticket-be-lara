<?php

namespace App\Services;

use App\Models\OrganizerMember;
use Illuminate\Auth\AuthenticationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class OrganizerAuthService
{
    public function login(string $uid, string $password): array
    {
        $token = auth('organizer')->attempt([
            'uid'      => $uid,
            'password' => $password,
        ]);

        if (! $token) {
            throw new AuthenticationException('Invalid organizer ID or password.');
        }

        /** @var OrganizerMember $member */
        $member = auth('organizer')->user();

        if (! $member->is_active) {
            auth('organizer')->logout();
            throw new AuthenticationException('This account is inactive.');
        }

        return ['token' => $token, 'member' => $member];
    }

    public function logout(): void
    {
        auth('organizer')->logout();
    }
}
