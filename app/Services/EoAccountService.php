<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Turning a platform account into an event organizer.
 *
 * Self-serve by design: the approval that matters is per-event verification by
 * a SUPER_ADMIN, which is where the actual risk sits. Gating account activation
 * as well would mean two review queues for one outcome.
 */
class EoAccountService
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    /**
     * Idempotent — re-activating an existing organizer is a no-op that still
     * returns a usable token.
     *
     * @return array{token: string, user: User, already_active: bool}
     */
    public function activate(User $user): array
    {
        $alreadyActive = (bool) $user->is_organizer;

        if (! $alreadyActive) {
            $user->is_organizer = true;
            $user->save();
        }

        // The old token still claims is_organizer:false and would keep the EO
        // app locked out until it expired, so mint a replacement.
        JWTAuth::factory()->setTTL(config('jwt.ttl'));
        $token = JWTAuth::fromUser($user->fresh());

        return [
            'token'          => $token,
            'user'           => $user->fresh(),
            'already_active' => $alreadyActive,
        ];
    }
}
