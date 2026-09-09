<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminOrderResource;
use App\Services\AuditLogger;
use App\Services\OrderService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class AdminOrderController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly OrderService $service, private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'event_id', 'buyer_uid', 'from', 'to', 'search']);

        if ($request->filled('requires_refund')) {
            $filters['requires_refund'] = $request->boolean('requires_refund');
        }

        $paginator = $this->service->listForConsole((int) $request->query('limit', 15), $filters);

        return $this->paginated('Orders retrieved.', AdminOrderResource::collection($paginator), $paginator);
    }

    public function show(string $orderNumber): JsonResponse
    {
        try {
            $order = $this->service->findForConsole($orderNumber);
        } catch (RuntimeException $exception) {
            return $this->error($exception->getMessage(), 404);
        }

        return $this->success('Order retrieved.', new AdminOrderResource($order));
    }

    public function cancel(string $orderNumber): JsonResponse
    {
        try {
            $order = $this->service->cancelFromConsole($orderNumber);
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422, ['order' => [$exception->getMessage()]]);
        } catch (RuntimeException $exception) {
            return $this->error($exception->getMessage(), 404);
        }

        $this->audit->record('order.cancelled', 'order', $order->order_number, $order->buyer_name, [
            'event_id' => $order->event_id, 'total_price' => (float) $order->total_price,
        ]);

        return $this->success('Order cancelled.', new AdminOrderResource($order));
    }

    public function settleRefund(string $orderNumber): JsonResponse
    {
        try {
            $order = $this->service->settleRefund($orderNumber);
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422, ['order' => [$exception->getMessage()]]);
        } catch (RuntimeException $exception) {
            return $this->error($exception->getMessage(), 404);
        }

        // Worth recording precisely: settling only clears a flag here, the money moves elsewhere.
        $this->audit->record('order.refund_settled', 'order', $order->order_number, $order->buyer_name, [
            'event_id' => $order->event_id, 'total_price' => (float) $order->total_price,
        ]);

        return $this->success('Refund marked as settled.', new AdminOrderResource($order));
    }
}
