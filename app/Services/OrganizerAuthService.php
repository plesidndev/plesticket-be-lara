<?php

namespace App\Services;

use App\Models\OrganizerMember;
use Illuminate\Auth\AuthenticationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class OrganizerAuthService
{
    public function login(string $uid, string $password): array
    {
        // Scope the longer crew TTL to this guard so it never widens the
        // platform `api` guard's tokens.
        $guard = auth('organizer');
        $ttl   = config('auth.guards.organizer.ttl');

        if ($ttl !== null) {
            $guard->setTTL((int) $ttl);
        }

        $token = $guard->attempt([
            'uid'      => $uid,
            'password' => $password,
        ]);

        if (! $token) {
            throw new AuthenticationException('Invalid organizer ID or password.');
        }

        /** @var OrganizerMember $member */
        $member = $guard->user();

        if (! $member->is_active) {
            $guard->logout();
            throw new AuthenticationException('This account is inactive.');
        }

        return ['token' => $token, 'member' => $member];
    }

    public function logout(): void
    {
        auth('organizer')->logout();
    }
}
