<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizerMember\AddMemberRequest;
use App\Http\Requests\OrganizerMember\UpdateMemberRequest;
use App\Http\Resources\OrganizerMemberResource;
use App\Services\OrganizerMemberService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class OrganizerMemberController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly OrganizerMemberService $service) {}

    public function index(Request $request, string $eventId): JsonResponse
    {
        try {
            $paginator = $this->service->list($eventId, auth('api')->id(), (int) $request->query('limit', 15));
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }

        return $this->paginated('Members retrieved.', OrganizerMemberResource::collection($paginator), $paginator);
    }

    public function store(AddMemberRequest $request, string $eventId): JsonResponse
    {
        try {
            $member = $this->service->add($eventId, auth('api')->id(), $request->validated());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->created('Member added.', new OrganizerMemberResource($member));
    }

    public function update(UpdateMemberRequest $request, string $eventId, int $memberId): JsonResponse
    {
        try {
            $member = $this->service->update($eventId, auth('api')->id(), $memberId, $request->validated());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success('Member updated.', new OrganizerMemberResource($member));
    }

    public function destroy(string $eventId, int $memberId): JsonResponse
    {
        try {
            $this->service->remove($eventId, auth('api')->id(), $memberId);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }

        return $this->success('Member removed.');
    }
}
