<?php

namespace Tests\Feature;

use App\Mail\LoginOtpMail;
use App\Models\LoginOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordlessAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_fixed_otp_requires_a_request_and_is_single_use(): void
    {
        Mail::fake();
        $this->app->instance('env', 'local');
        config()->set('auth.otp.local_code', '131313');
        $payload = ['email' => 'local@example.com', 'code' => '131313'];

        $this->postJson('/api/plesconnect-auth/otp/verify', $payload)->assertUnauthorized();
        $this->postJson('/api/plesconnect-auth/otp/request', ['email' => $payload['email']])->assertOk();
        Mail::assertSent(LoginOtpMail::class, fn (LoginOtpMail $mail) => $mail->code === '131313');
        $this->postJson('/api/plesconnect-auth/otp/verify', $payload)->assertOk();
        $this->postJson('/api/plesconnect-auth/otp/verify', $payload)->assertUnauthorized();
    }

    public function test_fixed_otp_is_ignored_outside_local(): void
    {
        Mail::fake();
        $this->app->instance('env', 'production');
        // Outside the random generator's range, so this assertion is deterministic.
        config()->set('auth.otp.local_code', '000000');

        $this->postJson('/api/plesconnect-auth/otp/request', ['email' => 'production@example.com'])->assertOk();
        Mail::assertSent(LoginOtpMail::class, fn (LoginOtpMail $mail) => preg_match('/^[1-9][0-9]{5}$/', $mail->code) === 1);
        $this->postJson('/api/plesconnect-auth/otp/verify', [
            'email' => 'production@example.com', 'code' => '000000',
        ])->assertUnauthorized();
    }

    public function test_email_otp_registers_and_logs_in_a_plesconnect_user(): void
    {
        Mail::fake();

        $this->postJson('/api/plesconnect-auth/otp/request', [
            'email' => 'New.User@example.com',
            'name' => 'New User',
        ])->assertOk();

        $code = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use (&$code) {
            $code = $mail->code;

            return $mail->hasTo('new.user@example.com');
        });

        $this->postJson('/api/plesconnect-auth/otp/verify', [
            'email' => 'new.user@example.com',
            'code' => $code,
        ])->assertOk()->assertJsonPath('data.user.email', 'new.user@example.com')
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $user = User::where('email', 'new.user@example.com')->firstOrFail();
        $this->assertTrue($user->is_plesconnect_user);
        $this->assertFalse($user->is_organizer);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull(LoginOtp::first()->consumed_at);
    }

    public function test_an_otp_is_single_use(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'person@example.com', 'is_organizer' => true]);
        $this->postJson('/api/plesconnect-auth/otp/request', ['email' => 'person@example.com']);
        $code = Mail::sent(LoginOtpMail::class)->first()->code;

        $payload = ['email' => 'person@example.com', 'code' => $code];
        $this->postJson('/api/plesconnect-auth/otp/verify', $payload)->assertOk();
        $this->postJson('/api/plesconnect-auth/otp/verify', $payload)->assertUnauthorized();
    }

    public function test_wrong_otp_attempts_are_recorded(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'person@example.com', 'is_organizer' => true]);
        $this->postJson('/api/plesconnect-auth/otp/request', ['email' => 'person@example.com']);

        $this->postJson('/api/plesconnect-auth/otp/verify', [
            'email' => 'person@example.com',
            'code' => '000000',
        ])->assertUnauthorized();

        $this->assertSame(1, LoginOtp::first()->attempts);
    }

    public function test_google_oauth_logs_in_with_a_verified_email(): void
    {
        config()->set('services.google', [
            'client_id' => 'client-id',
            'client_secret' => 'secret',
            'redirect' => 'https://api.example.com/api/plesconnect-auth/google/callback',
            'frontend_callback' => 'https://connect.example.com/auth/google/callback',
            'handoff_ttl_seconds' => 120,
        ]);
        $url = $this->getJson('/api/plesconnect-auth/google/redirect')->assertOk()->json('data.url');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token']),
            'openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-123',
                'email' => 'google@example.com',
                'email_verified' => true,
                'name' => 'Google User',
            ]),
        ]);

        $callback = $this->get('/api/plesconnect-auth/google/callback?'.http_build_query([
            'code' => 'authorization-code',
            'state' => $query['state'],
        ]))->assertRedirect();

        $location = $callback->headers->get('Location');
        $this->assertStringStartsWith('https://connect.example.com/auth/google/callback?code=', $location);
        $this->assertStringNotContainsString('eyJ', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $callbackQuery);

        $payload = ['code' => $callbackQuery['code']];
        $this->postJson('/api/plesconnect-auth/google/exchange', $payload)
            ->assertOk()
            ->assertJsonPath('data.user.email', 'google@example.com')
            ->assertJsonPath('data.user.is_plesconnect_user', true)
            ->assertJsonPath('data.user.is_organizer', false)
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $this->postJson('/api/plesconnect-auth/google/exchange', $payload)->assertUnauthorized();

        $this->assertDatabaseHas('social_accounts', [
            'provider' => 'google',
            'provider_user_id' => 'google-123',
        ]);
    }

    public function test_existing_buyer_can_join_plesconnect_without_becoming_an_organizer(): void
    {
        Mail::fake();
        $buyer = User::factory()->create([
            'email' => 'buyer@example.com',
            'password' => 'password123',
            'is_organizer' => false,
        ]);

        $this->postJson('/api/plesconnect-auth/otp/request', ['email' => $buyer->email])->assertOk();
        $code = Mail::sent(LoginOtpMail::class)->first()->code;

        $this->postJson('/api/plesconnect-auth/otp/verify', [
            'email' => $buyer->email,
            'code' => $code,
        ])->assertOk()
            ->assertJsonPath('data.user.is_plesconnect_user', true)
            ->assertJsonPath('data.user.is_organizer', false);

        $buyer->refresh();
        $this->assertTrue($buyer->is_plesconnect_user);
        $this->assertFalse($buyer->is_organizer);

        $this->postJson('/api/auth/login', [
            'email' => $buyer->email,
            'password' => 'password123',
        ])->assertOk();
    }

    public function test_organizer_cannot_use_password_login(): void
    {
        $organizer = User::factory()->create([
            'email' => 'organizer@example.com',
            'password' => 'password123',
            'is_organizer' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $organizer->email,
            'password' => 'password123',
        ])->assertUnauthorized();
    }
}
