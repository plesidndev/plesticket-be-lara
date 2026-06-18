<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgentOrderResource;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Repositories\Contracts\OrganizerMemberRepositoryInterface;
use App\Services\OrderService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class EoAgentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly OrderService $orders,
        private readonly EventRepositoryInterface $events,
        private readonly OrganizerMemberRepositoryInterface $members,
    ) {}

    public function orders(Request $request, string $eventId): JsonResponse
    {
        $event = $this->resolveEvent($eventId);

        $paginator = $this->orders->listAgentOrdersByEvent(
            $event->id,
            (int) $request->query('limit', 15),
            $request->query('search'),
        );

        return $this->paginated('Agent orders retrieved.', AgentOrderResource::collection($paginator), $paginator);
    }

    public function agentOrders(Request $request, string $eventId, int $agentId): JsonResponse
    {
        $event = $this->resolveEvent($eventId);

        $agent = $this->members->findById($event->id, $agentId);

        if (! $agent) {
            return $this->error('Agent not found.', 404);
        }

        $paginator = $this->orders->listByAgent(
            $agent->id,
            (int) $request->query('limit', 15),
            $request->query('search'),
        );

        return $this->paginated('Agent orders retrieved.', AgentOrderResource::collection($paginator), $paginator);
    }

    public function agentSummary(string $eventId, int $agentId): JsonResponse
    {
        $event = $this->resolveEvent($eventId);

        $agent = $this->members->findById($event->id, $agentId);

        if (! $agent) {
            return $this->error('Agent not found.', 404);
        }

        $data = $this->orders->agentSummary($agent->id, null, null);

        return $this->success('Agent summary retrieved.', [
            'agent'              => [
                'id'              => $agent->id,
                'uid'             => $agent->uid,
                'name'            => $agent->name,
                'commission_rate' => (float) $agent->commission_rate,
            ],
            'total_orders'       => $data['total_orders'],
            'total_tickets_sold' => $data['total_tickets_sold'],
            'total_revenue'      => $data['total_revenue'],
            'commission_owed'    => round($data['total_revenue'] * (float) $agent->commission_rate / 100, 2),
        ]);
    }

    public function summary(string $eventId): JsonResponse
    {
        $event = $this->resolveEvent($eventId);

        $summary = $this->orders->agentsSummaryByEvent($event->id);

        return $this->success('Agents summary retrieved.', $summary);
    }

    private function resolveEvent(string $eventId)
    {
        $event = $this->events->findById($eventId);

        if (! $event || $event->user_id !== auth('api')->id()) {
            throw new RuntimeException('Event not found.');
        }

        return $event;
    }
}
