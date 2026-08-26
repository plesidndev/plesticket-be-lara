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
     * Returns a replacement token: the caller's current one carries
     * is_organizer:false and would be refused by the `eo` middleware until it
     * expired. Clients must swap it in.
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
