<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a deactivated crew member out on their next request.
 *
 * OrganizerAuthService checks `is_active` at login, but crew tokens are minted
 * with the long `auth.guards.organizer.ttl` so a gate officer is not signed out
 * mid-shift. Without a per-request check, an owner who deactivates a member
 * hands them up to that full TTL of continued scanning — the flag would only
 * bite whenever the JWT happened to expire.
 *
 * Aliased as `organizer.active`. Separate from RoleMiddleware, which compares
 * role values and cannot express a boolean flag.
 */
class EnsureActiveOrganizerMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $member = auth('organizer')->user();

        if (! $member) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $member->is_active) {
            // Distinct code so the crew app can drop its stored token and send
            // the member back to the login screen instead of retrying.
            return response()->json([
                'status'  => 'error',
                'message' => 'This account is inactive.',
                'errors'  => ['code' => 'ORGANIZER_MEMBER_INACTIVE'],
            ], 403);
        }

        return $next($request);
    }
}
