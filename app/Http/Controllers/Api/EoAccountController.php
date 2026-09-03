<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\EoAccountService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class EoAccountController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly EoAccountService $service) {}

    /**
     * Activates the signed-in account as an event organizer.
     *
     * Returns a replacement token. This is a convenience, not a requirement:
     * the `eo` middleware reads is_organizer from the database, so the caller's
     * existing token authorizes fine straight away. The replacement only keeps
     * a client that decodes the JWT from reading a stale is_organizer:false.
     */
    public function activate(): JsonResponse
    {
        $result = $this->service->activate(auth('api')->user());

        return $this->success(
            $result['already_active']
                ? 'This account is already an event organizer.'
                : 'Event organizer access activated.',
            [
                'token' => $result['token'],
                'user'  => new UserResource($result['user']),
            ],
        );
    }
}
