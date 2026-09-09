<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayoutResource;
use App\Models\Payout;
use App\Services\AuditLogger;
use App\Services\PayoutService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class PayoutController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly PayoutService $service, private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $query = Payout::with('organizer')->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('organizer_uid')) {
            $query->whereHas('organizer', fn ($organizer) => $organizer->where('uid', $request->query('organizer_uid')));
        }

        $paginator = $query->paginate((int) $request->query('limit', 15));

        return $this->paginated('Payouts retrieved.', PayoutResource::collection($paginator), $paginator);
    }

    /** An organizer's own payouts. Scoped to the caller, so no ownership check is needed downstream. */
    public function mine(Request $request): JsonResponse
    {
        $paginator = Payout::with('organizer')
            ->where('organizer_id', auth('api')->id())
            ->latest('created_at')
            ->paginate((int) $request->query('limit', 15));

        return $this->paginated('Payouts retrieved.', PayoutResource::collection($paginator), $paginator);
    }

    public function show(string $id): JsonResponse
    {
        try {
            $payout = $this->service->find($id);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }

        return $this->success('Payout retrieved.', new PayoutResource($payout));
    }

    /** What a payout would contain, so it can be checked before anything is committed. */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'organizer_id' => ['required', 'integer'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return $this->success('Payout preview retrieved.', $this->service->preview((int) $data['organizer_id'], $data['from'], $data['to']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'organizer_id' => ['required', 'integer'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        try {
            $payout = $this->service->create((int) $data['organizer_id'], $data['from'], $data['to']);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422, ['payout' => [$e->getMessage()]]);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }

        $this->audit->record('payout.created', 'payout', $payout->reference, $payout->organizer?->name, [
            'net_amount' => (float) $payout->net_amount, 'orders' => $payout->lines->count(),
        ]);

        return $this->created('Payout created.', new PayoutResource($payout));
    }

    public function approve(string $id): JsonResponse
    {
        return $this->transition($id, fn () => $this->service->approve($id, auth('api')->id()), 'payout.approved', 'Payout approved.');
    }

    public function markPaid(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['bank_reference' => ['required', 'string', 'max:100']]);

        return $this->transition($id, fn () => $this->service->markPaid($id, auth('api')->id(), $data['bank_reference']), 'payout.paid', 'Payout marked paid.');
    }

    public function cancel(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['required', 'string'], 'note' => ['nullable', 'string', 'max:500']]);

        return $this->transition($data['id'], fn () => $this->service->cancel($data['id'], $data['note'] ?? null), 'payout.cancelled', 'Payout cancelled.');
    }

    private function transition(string $id, callable $act, string $action, string $message): JsonResponse
    {
        try {
            $payout = $act();
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422, ['payout' => [$e->getMessage()]]);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }

        $this->audit->record($action, 'payout', $payout->reference, $payout->organizer?->name, ['net_amount' => (float) $payout->net_amount]);

        return $this->success($message, new PayoutResource($payout));
    }
}
