<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly OrderService $service) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->listByBuyer(
            auth('api')->id(),
            (int) $request->query('limit', 15)
        );

        return $this->paginated('Orders retrieved.', OrderResource::collection($paginator), $paginator);
    }

    public function store(CreateOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->service->create(auth('api')->id(), $request->validated());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->created('Order created.', new OrderResource($order));
    }

    public function show(string $orderNumber): JsonResponse
    {
        try {
            $order = $this->service->findByOrderNumber($orderNumber, auth('api')->id());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }

        return $this->success('Order retrieved.', new OrderResource($order));
    }

    public function pay(Request $request, string $orderNumber): JsonResponse
    {
        $request->validate(['payment_method' => ['nullable', 'string', 'max:50']]);

        try {
            $order = $this->service->pay($orderNumber, auth('api')->id(), $request->input('payment_method'));
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success('Payment successful.', new OrderResource($order));
    }

    public function cancel(string $orderNumber): JsonResponse
    {
        try {
            $order = $this->service->cancel($orderNumber, auth('api')->id());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success('Order cancelled.', new OrderResource($order));
    }
}
