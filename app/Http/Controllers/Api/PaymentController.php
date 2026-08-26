<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\CreatePaymentRequest;
use App\Http\Resources\PaymentMethodResource;
use App\Http\Resources\PaymentResource;
use App\Services\PaymentService;
use App\Services\Payments\PaymentGatewayException;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use RuntimeException;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly PaymentService $service) {}

    /**
     * Enabled payment methods, grouped by type for the checkout picker.
     */
    public function methods(): JsonResponse
    {
        $groups = $this->service->availableMethods()
            ->map(fn(array $group) => [
                'type'    => $group['type'],
                'label'   => $group['label'],
                'methods' => PaymentMethodResource::collection($group['methods']),
            ]);

        return $this->success('Payment methods retrieved.', $groups);
    }

    public function store(CreatePaymentRequest $request, string $orderNumber): JsonResponse
    {
        try {
            $payment = $this->service->createForOrder(
                $orderNumber,
                auth('api')->id(),
                $request->validated()['method_code'],
            );
        } catch (PaymentGatewayException $e) {
            // Must precede RuntimeException — it is a subclass. The provider
            // rejected or could not be reached; not the caller's fault.
            return $this->error($e->getMessage(), 502);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->created('Payment created.', new PaymentResource($payment));
    }

    /**
     * Polled by the checkout screen while the buyer completes the transfer.
     */
    public function show(string $orderNumber): JsonResponse
    {
        try {
            $payment = $this->service->findForBuyer($orderNumber, auth('api')->id());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }

        return $this->success('Payment retrieved.', new PaymentResource($payment));
    }
}
