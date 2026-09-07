<?php

namespace App\Services;

use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class GoogleAuthHandoffService
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    public function create(string $token, int $userId): string
    {
        $code = Str::random(64);
        $ttl = max(30, (int) config('services.google.handoff_ttl_seconds', 120));

        Cache::put($this->cacheKey($code), [
            'token' => $token,
            'user_id' => $userId,
        ], now()->addSeconds($ttl));

        return $code;
    }

    public function consume(string $code): array
    {
        $handoff = Cache::pull($this->cacheKey($code));
        if (! is_array($handoff) || empty($handoff['token']) || empty($handoff['user_id'])) {
            throw new AuthenticationException('Invalid or expired Google login code.');
        }

        $user = $this->users->findById((int) $handoff['user_id']);
        if (! $user || ! $user->is_plesconnect_user || ! $user->is_active) {
            throw new AuthenticationException('This account cannot access PlesConnect.');
        }

        return ['token' => $handoff['token'], 'user' => $user];
    }

    private function cacheKey(string $code): string
    {
        return 'google-auth-handoff:'.hash('sha256', $code);
    }
}
