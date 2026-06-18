<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentSummaryController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly OrderService $service) {}

    public function __invoke(Request $request): JsonResponse
    {
        $agent = auth('organizer')->user();
        $from  = $request->query('from');
        $to    = $request->query('to');

        $summary = $this->service->agentSummary(
            $agent->id,
            (float) $agent->commission_rate,
            $from,
            $to,
        );

        return $this->success('Summary retrieved.', $summary);
    }
}
