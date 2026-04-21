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
        $user = auth('api')->user();

        if (! $user || ! in_array($user->role->value, $roles, true)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Insufficient permissions.',
            ], 403);
        }

        return $next($request);
    }
}