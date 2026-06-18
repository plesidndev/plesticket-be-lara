<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Http\Resources\TicketTypeResource;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class AgentEventController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly EventRepositoryInterface $events) {}

    public function show(): JsonResponse
    {
        $agent = auth('organizer')->user();

        $event = $this->events->findById($agent->event_id);

        if (! $event) {
            throw new RuntimeException('Event not found.');
        }

        return $this->success('Event retrieved.', new EventResource($event));
    }

    public function ticketTypes(): JsonResponse
    {
        $agent = auth('organizer')->user();

        $event = $this->events->findById($agent->event_id);

        if (! $event) {
            return $this->error('Event not found.', 404);
        }

        $types = $event->ticketTypes()
            ->where('is_active', true)
            ->where('quota', '>', 0)
            ->get();

        return $this->success('Ticket types retrieved.', TicketTypeResource::collection($types));
    }
}
