<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketType\CreateTicketTypeRequest;
use App\Http\Requests\TicketType\UpdateTicketTypeRequest;
use App\Http\Resources\TicketTypeResource;
use App\Services\TicketTypeService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use RuntimeException;

class TicketTypeController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TicketTypeService $service) {}

    public function store(CreateTicketTypeRequest $request, string $eventId): JsonResponse
    {
        try {
            $ticketType = $this->service->add($eventId, auth('api')->id(), $request->validated());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->created('Ticket type added.', new TicketTypeResource($ticketType));
    }

    public function update(UpdateTicketTypeRequest $request, string $eventId, int $ticketTypeId): JsonResponse
    {
        try {
            $ticketType = $this->service->update($eventId, auth('api')->id(), $ticketTypeId, $request->validated());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success('Ticket type updated.', new TicketTypeResource($ticketType));
    }

    public function destroy(string $eventId, int $ticketTypeId): JsonResponse
    {
        try {
            $this->service->remove($eventId, auth('api')->id(), $ticketTypeId);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success('Ticket type removed.');
    }
}
