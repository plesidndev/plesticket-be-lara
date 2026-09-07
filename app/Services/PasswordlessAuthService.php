<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Mail\LoginOtpMail;
use App\Models\LoginOtp;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tymon\JWTAuth\Facades\JWTAuth;

class PasswordlessAuthService
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    public function request(string $email, ?string $name): void
    {
        $email = strtolower(trim($email));
        $localCode = (string) config('auth.otp.local_code', '');
        $code = app()->environment('local') && preg_match('/\A[0-9]{6}\z/', $localCode)
            ? $localCode
            : (string) random_int(100000, 999999);
        $minutes = (int) config('auth.otp.expire_minutes', 10);

        DB::transaction(function () use ($email, $name, $code, $minutes) {
            LoginOtp::where('email', $email)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            LoginOtp::create([
                'email' => $email,
                'name' => $name,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes($minutes),
            ]);
        });

        Mail::to($email)->send(new LoginOtpMail($code, $minutes));
    }

    public function verify(string $email, string $code): array
    {
        $email = strtolower(trim($email));

        $result = DB::transaction(function () use ($email, $code) {
            $otp = LoginOtp::where('email', $email)->whereNull('consumed_at')->latest('id')->lockForUpdate()->first();
            $maxAttempts = (int) config('auth.otp.max_attempts', 5);

            if (! $otp || $otp->expires_at->isPast() || $otp->attempts >= $maxAttempts) {
                throw new AuthenticationException('Invalid or expired login code.');
            }

            if (! Hash::check($code, $otp->code_hash)) {
                $otp->increment('attempts');

                return null;
            }

            $otp->update(['consumed_at' => now()]);
            $user = $this->users->findByEmail($email);
            if (! $user) {
                $user = $this->users->create([
                    'name' => $otp->name ?: str($email)->before('@')->replace(['.', '_', '-'], ' ')->title(),
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

            if (! $user->is_active) {
                throw new AuthenticationException('Account is inactive.');
            }

            return ['token' => JWTAuth::fromUser($user), 'user' => $user];
        });

        if (! $result) {
            throw new AuthenticationException('Invalid or expired login code.');
        }

        return $result;
    }
}
