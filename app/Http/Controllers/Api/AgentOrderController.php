<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\AgentCreateOrderRequest;
use App\Http\Resources\AgentOrderResource;
use App\Services\OrderService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class AgentOrderController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly OrderService $service) {}

    public function index(Request $request): JsonResponse
    {
        $agent    = auth('organizer')->user();
        $search   = $request->query('search');
        $perPage  = (int) $request->query('limit', 15);

        $paginator = $this->service->listByAgent($agent->id, $perPage, $search);

        return $this->paginated('Orders retrieved.', AgentOrderResource::collection($paginator), $paginator);
    }

    public function store(AgentCreateOrderRequest $request): JsonResponse
    {
        $agent = auth('organizer')->user();

        try {
            $order = $this->service->createAgentOrder(
                $agent->id,
                array_merge($request->validated(), ['event_id' => $agent->event_id]),
            );
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->created('Order created.', new AgentOrderResource($order));
    }

    public function show(string $orderNumber): JsonResponse
    {
        $agent = auth('organizer')->user();

        try {
            $order = $this->service->findByOrderNumberForAgent($orderNumber, $agent->id);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }

        return $this->success('Order retrieved.', new AgentOrderResource($order));
    }
}
