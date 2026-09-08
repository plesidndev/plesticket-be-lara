<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCatalogAccess
{
    public function handle(Request $request, Closure $next, string $audience = 'customer'): Response
    {
        $user = $request->user('api');
        $hasAccess = $audience === 'admin'
            ? (bool) $user?->role?->isStaff()
            : $user?->is_plesconnect_user;
        abort_unless($user && $user->is_active && $hasAccess, 403, 'An active account with catalog access is required.');

        return $next($request);
    }
}
