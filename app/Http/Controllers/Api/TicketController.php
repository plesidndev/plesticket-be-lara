<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Services\OrderService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use RuntimeException;

class TicketController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly OrderService $service) {}

    // Buyer or organizer can look up a ticket by code
    public function show(string $code): JsonResponse
    {
        try {
            $ticket = $this->service->getTicket($code);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }

        return $this->success('Ticket retrieved.', new TicketResource($ticket));
    }

    // Gate officer scans ticket
    public function scan(string $code): JsonResponse
    {
        $member = auth('organizer')->user();

        try {
            $ticket = $this->service->scanTicket($code, $member->id, $member->event_id);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success('Ticket scanned.', new TicketResource($ticket));
    }
}
