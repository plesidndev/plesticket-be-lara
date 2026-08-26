<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\Payments\PaymentGatewayException;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly OrderService $service,
        private readonly PaymentService $payments,
    ) {}

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
        $order = null;

        try {
            $data = $request->validated();
            $order = $this->service->create(auth('api')->id(), $data);

            if (! isset($data['payment_method'])) {
                return $this->created('Order created.', new OrderResource($order));
            }

            $payment = $this->payments->createForOrder(
                $order->order_number,
                auth('api')->id(),
                $data['payment_method'],
            );
        } catch (PaymentGatewayException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'data' => [
                    'order' => $order ? new OrderResource($order) : null,
                    'payment' => null,
                    'payment_retry_url' => $order
                        ? url("/api/orders/{$order->order_number}/payments")
                        : null,
                ],
            ], 502);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->created('Order and payment created.', [
            'order' => new OrderResource($order),
            'payment' => new PaymentResource($payment),
        ]);
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
