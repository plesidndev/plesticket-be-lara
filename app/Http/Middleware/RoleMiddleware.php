<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    use ApiResponse;

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Check platform guard first, then organizer guard
        $user = auth('api')->user() ?? auth('organizer')->user();

        if (! $user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! in_array($user->role->value, $roles, true)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Insufficient permissions.',
            ], 403);
        }

        return $next($request);
    }
}
