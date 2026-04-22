<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizerAuth\LoginOrganizerRequest;
use App\Http\Resources\OrganizerMemberResource;
use App\Services\OrganizerAuthService;
use App\Traits\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;

class OrganizerAuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly OrganizerAuthService $auth) {}

    public function login(LoginOrganizerRequest $request): JsonResponse
    {
        try {
            $result = $this->auth->login($request->uid, $request->password);
        } catch (AuthenticationException $e) {
            return $this->error($e->getMessage(), 401);
        }

        return $this->success('Login successful.', [
            'token'  => $result['token'],
            'member' => new OrganizerMemberResource($result['member']),
        ]);
    }

    public function me(): JsonResponse
    {
        return $this->success('ok', new OrganizerMemberResource(auth('organizer')->user()));
    }

    public function logout(): JsonResponse
    {
        $this->auth->logout();

        return $this->success('Logged out successfully.');
    }
}
