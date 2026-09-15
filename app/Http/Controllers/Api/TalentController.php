<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\IdempotentRequestInFlight;
use App\Http\Controllers\Controller;
use App\Http\Requests\Talent\CreateTalentRequest;
use App\Http\Requests\Talent\UpdateTalentRequest;
use App\Http\Resources\TalentResource;
use App\Services\AuditLogger;
use App\Services\IdempotencyGuard;
use App\Services\TalentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class TalentController extends Controller
{
    use ApiResponse;

    private const CREATE_SCOPE = 'talents.create';

    public function __construct(private readonly TalentService $service, private readonly AuditLogger $audit, private readonly IdempotencyGuard $idempotency) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list(
            (int) $request->query('limit', 20),
            $request->only(['search', 'type', 'category'])
        );

        return $this->paginated('Talents retrieved.', TalentResource::collection($paginator), $paginator);
    }

    public function mine(Request $request): JsonResponse
    {
        $paginator = $this->service->mine(
            auth('api')->id(),
            (int) $request->query('limit', 20),
            $request->only(['search'])
        );

        return $this->paginated('My talents retrieved.', TalentResource::collection($paginator), $paginator);
    }

    /**
     * A creator watching a slow save presses Create again, and both presses used to become their
     * own talent. Sending the same Idempotency-Key with each attempt collapses them into one.
     * The header is optional, so callers that do not send it keep the previous behaviour.
     */
    public function store(CreateTalentRequest $request): JsonResponse
    {
        $userId = auth('api')->id();
        $key = trim((string) $request->header('Idempotency-Key'));

        if ($key === '') {
            return $this->created('Talent created.', new TalentResource($this->service->create($userId, $request->validated())));
        }
        if (mb_strlen($key) > 100) {
            return $this->error('Idempotency-Key must be 100 characters or fewer.', 422);
        }

        try {
            $existingId = $this->idempotency->claim(self::CREATE_SCOPE, $userId, $key);
        } catch (IdempotentRequestInFlight) {
            return $this->error('An identical request is still being processed.', 409);
        }

        if ($existingId !== null) {
            return $this->success('Talent already created.', new TalentResource($this->service->findOrFail($existingId)));
        }

        try {
            $talent = $this->service->create($userId, $request->validated());
        } catch (\Throwable $error) {
            $this->idempotency->release(self::CREATE_SCOPE, $userId, $key);

            throw $error;
        }
        $this->idempotency->complete(self::CREATE_SCOPE, $userId, $key, $talent->id);

        return $this->created('Talent created.', new TalentResource($talent));
    }

    public function show(int $id): JsonResponse
    {
        try {
            $talent = $this->service->findOrFail($id);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }

        return $this->success('Talent retrieved.', new TalentResource($talent));
    }

    public function update(UpdateTalentRequest $request, int $id): JsonResponse
    {
        $isAdmin = (bool) auth('api')->user()?->role->isStaff();

        try {
            $talent = $this->service->update($id, auth('api')->id(), $isAdmin, $request->validated());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 403);
        }

        return $this->success('Talent updated.', new TalentResource($talent));
    }

    public function destroy(int $id): JsonResponse
    {
        $isAdmin = (bool) auth('api')->user()?->role->isStaff();

        try {
            $this->service->delete($id, auth('api')->id(), $isAdmin);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 403);
        }

        return $this->success('Talent deleted.');
    }

    // Super Admin only
    public function adminIndex(Request $request): JsonResponse
    {
        $filters = $request->only(['search']);
        if ($request->has('is_verified')) {
            $filters['is_verified'] = filter_var($request->query('is_verified'), FILTER_VALIDATE_BOOLEAN);
        }

        $paginator = $this->service->adminList(
            (int) $request->query('limit', 20),
            $filters
        );

        return $this->paginated('Talents retrieved.', TalentResource::collection($paginator), $paginator);
    }

    public function verify(int $id): JsonResponse
    {
        try {
            $talent = $this->service->verify($id, auth('api')->id());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $this->audit->record('talent.verified', 'talent', (string) $talent->id, $talent->name);

        return $this->success('Talent verified.', new TalentResource($talent));
    }
}
