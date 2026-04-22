<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\CreateEventRequest;
use App\Http\Requests\Event\RejectEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Services\EventService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class EventController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly EventService $service) {}

    // Public — list verified events
    public function index(Request $request): JsonResponse
    {
        $filters   = $request->only(['search', 'category', 'city', 'is_online']);
        $paginator = $this->service->listPublic((int) $request->query('limit', 15), $filters);

        return $this->paginated('Events retrieved.', EventResource::collection($paginator), $paginator);
    }

    // Public — get event detail by slug
    public function showBySlug(string $slug): JsonResponse
    {
        try {
            $event = $this->service->findBySlug($slug);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }

        return $this->success('Event retrieved.', new EventResource($event));
    }

    // REGISTERED_USER — list own events
    public function myEvents(Request $request): JsonResponse
    {
        $paginator = $this->service->listByUser(auth('api')->id(), (int) $request->query('limit', 15));

        return $this->paginated('Events retrieved.', EventResource::collection($paginator), $paginator);
    }

    // REGISTERED_USER — create event
    public function store(CreateEventRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['pic_identity_number'] = $data['pic_identity_number'];
        $data['pic_npwp']            = $data['pic_npwp'] ?? null;

        $event = $this->service->create(auth('api')->id(), $data);

        return $this->created('Event created.', new EventResource($event));
    }

    // REGISTERED_USER — update own event
    public function update(UpdateEventRequest $request, string $id): JsonResponse
    {
        try {
            $event = $this->service->update($id, auth('api')->id(), $request->validated());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success('Event updated.', new EventResource($event));
    }

    // REGISTERED_USER — toggle active/inactive
    public function toggleActive(string $id): JsonResponse
    {
        try {
            $event = $this->service->toggleActive($id, auth('api')->id());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }

        $label = $event->is_published ? 'activated' : 'deactivated';

        return $this->success("Event {$label}.", new EventResource($event));
    }

    // SUPER_ADMIN — list all events
    public function adminIndex(Request $request): JsonResponse
    {
        $filters   = $request->only(['verification_status', 'search']);
        $paginator = $this->service->listAdmin((int) $request->query('limit', 15), $filters);

        return $this->paginated('Events retrieved.', EventResource::collection($paginator), $paginator);
    }

    // SUPER_ADMIN — get single event
    public function adminShow(string $id): JsonResponse
    {
        try {
            $event = $this->service->findById($id);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }

        return $this->success('Event retrieved.', new EventResource($event));
    }

    // SUPER_ADMIN — verify
    public function verify(string $id): JsonResponse
    {
        try {
            $event = $this->service->verify($id, auth('api')->id());
        } catch (RuntimeException | InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success('Event verified.', new EventResource($event));
    }

    // SUPER_ADMIN — reject
    public function reject(RejectEventRequest $request, string $id): JsonResponse
    {
        try {
            $event = $this->service->reject($id, auth('api')->id(), $request->validated('reason'));
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }

        return $this->success('Event rejected.', new EventResource($event));
    }

    // SUPER_ADMIN — suspend
    public function suspend(string $id): JsonResponse
    {
        try {
            $event = $this->service->suspend($id, auth('api')->id());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }

        return $this->success('Event suspended.', new EventResource($event));
    }
}
