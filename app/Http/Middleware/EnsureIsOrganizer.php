<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates routes that only an event owner (EO) may reach.
 *
 * Aliased as `eo`, not `organizer`: in this codebase "organizer" already means
 * `organizer_members` — the staff an EO hires, who authenticate on their own
 * `auth:organizer` guard. This middleware is about the platform account that
 * owns events, which is a `users` row with `is_organizer = true`.
 *
 * Separate from RoleMiddleware, which compares UserRole values and cannot
 * express a boolean flag.
 */
class EnsureIsOrganizer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $user->is_organizer) {
            // The code lets the EO frontend route to onboarding rather than
            // showing a generic permission error — the account is valid, it
            // just has not been activated as an organizer yet.
            return response()->json([
                'status'  => 'error',
                'message' => 'This account is not an event organizer yet.',
                'errors'  => ['code' => 'EO_NOT_ACTIVATED'],
            ], 403);
        }

        return $next($request);
    }
}
