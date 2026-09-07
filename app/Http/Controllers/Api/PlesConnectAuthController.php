<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Services\GoogleAuthHandoffService;
use App\Services\GoogleAuthService;
use App\Services\PasswordlessAuthService;
use App\Traits\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlesConnectAuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PasswordlessAuthService $passwordless,
        private readonly GoogleAuthService $google,
        private readonly GoogleAuthHandoffService $googleHandoff,
    ) {}

    public function requestOtp(RequestOtpRequest $request): JsonResponse
    {
        $this->passwordless->request((string) $request->string('email'), $request->input('name'));

        return $this->success('If the email can receive messages, a login code has been sent.');
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        try {
            $result = $this->passwordless->verify((string) $request->string('email'), (string) $request->string('code'));
        } catch (AuthenticationException $e) {
            return $this->error($e->getMessage(), 401);
        }

        return $this->success('Login successful.', [
            'token' => $result['token'],
            'user' => new UserResource($result['user']),
        ]);
    }

    public function googleRedirect(): JsonResponse
    {
        return $this->success('Google authorization URL generated.', ['url' => $this->google->redirectUrl()]);
    }

    public function googleCallback(Request $request): RedirectResponse
    {
        $frontendCallback = (string) config('services.google.frontend_callback');

        if (! $request->filled(['code', 'state'])) {
            return redirect()->away($this->googleCallbackUrl($frontendCallback, ['error' => 'invalid_callback']));
        }

        try {
            $result = $this->google->callback((string) $request->string('code'), (string) $request->string('state'));
        } catch (AuthenticationException) {
            return redirect()->away($this->googleCallbackUrl($frontendCallback, ['error' => 'google_auth_failed']));
        }

        $code = $this->googleHandoff->create($result['token'], $result['user']->id);

        return redirect()->away($this->googleCallbackUrl($frontendCallback, ['code' => $code]));
    }

    public function googleExchange(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'size:64']]);

        try {
            $result = $this->googleHandoff->consume((string) $request->string('code'));
        } catch (AuthenticationException $e) {
            return $this->error($e->getMessage(), 401);
        }

        return $this->success('Login successful.', [
            'token' => $result['token'],
            'user' => new UserResource($result['user']),
        ]);
    }

    private function googleCallbackUrl(string $baseUrl, array $query): string
    {
        return $baseUrl.(str_contains($baseUrl, '?') ? '&' : '?').http_build_query($query);
    }
}
