<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\SocialAccount;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tymon\JWTAuth\Facades\JWTAuth;

class GoogleAuthService
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    public function redirectUrl(): string
    {
        $this->ensureConfigured();
        $state = Str::random(64);
        Cache::put('google-oauth-state:'.hash('sha256', $state), true, now()->addMinutes(10));

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);
    }

    public function callback(string $code, string $state): array
    {
        $this->ensureConfigured();
        if (! Cache::pull('google-oauth-state:'.hash('sha256', $state))) {
            throw new AuthenticationException('Invalid or expired Google OAuth state.');
        }

        $token = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect'),
            'grant_type' => 'authorization_code',
        ]);
        if (! $token->successful() || ! $token->json('access_token')) {
            throw new AuthenticationException('Google authentication failed.');
        }

        $profileResponse = Http::withToken($token->json('access_token'))
            ->get('https://openidconnect.googleapis.com/v1/userinfo');
        $profile = $profileResponse->json();
        if (! $profileResponse->successful() || empty($profile['sub']) || empty($profile['email']) || empty($profile['email_verified'])) {
            throw new AuthenticationException('Google did not provide a verified email address.');
        }

        return DB::transaction(function () use ($profile) {
            $account = SocialAccount::where('provider', 'google')->where('provider_user_id', $profile['sub'])->first();
            $email = strtolower(trim($profile['email']));
            $user = $account ? $this->users->findById($account->user_id) : $this->users->findByEmail($email);

            if (! $user) {
                $user = $this->users->create([
                    'name' => $profile['name'] ?? str($email)->before('@')->title(),
                    'email' => $email,
                    'email_verified_at' => now(),
                    'password' => null,
                    'role' => UserRole::RegisteredUser,
                    'is_plesconnect_user' => true,
                    'is_organizer' => false,
                    'is_active' => true,
                ]);
            } elseif (! $user->is_plesconnect_user || ! $user->email_verified_at) {
                $user = $this->users->update($user, [
                    'is_plesconnect_user' => true,
                    'email_verified_at' => $user->email_verified_at ?: now(),
                ]);
            }

            if (! $account) {
                $existing = SocialAccount::where('user_id', $user->id)->where('provider', 'google')->first();
                if ($existing && $existing->provider_user_id !== $profile['sub']) {
                    throw new AuthenticationException('This email is linked to another Google account.');
                }
                SocialAccount::firstOrCreate([
                    'provider' => 'google',
                    'provider_user_id' => $profile['sub'],
                ], ['user_id' => $user->id]);
            }

            if (! $user->is_active) {
                throw new AuthenticationException('Account is inactive.');
            }

            return ['token' => JWTAuth::fromUser($user), 'user' => $user];
        });
    }

    private function ensureConfigured(): void
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret') || ! config('services.google.redirect')) {
            throw new RuntimeException('Google OAuth is not configured.');
        }
    }
}
