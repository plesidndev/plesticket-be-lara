<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route on permission grants rather than on a role name.
 *
 * Several permissions passed together mean "any of these", matching how RoleMiddleware reads a
 * role list. Checks hit the database through User::permissionCodes(), which memoizes per request,
 * so a revocation takes effect on the caller's next request instead of when their token expires.
 */
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user('api');

        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Insufficient permissions.',
            'errors' => ['code' => 'MISSING_PERMISSION', 'required' => $permissions],
        ], 403);
    }
}
