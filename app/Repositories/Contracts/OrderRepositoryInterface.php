<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use Illuminate\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface
{
    public function paginateByBuyer(int $buyerId, int $perPage): LengthAwarePaginator;
    public function paginateByAgent(int $agentId, int $perPage, ?string $search): LengthAwarePaginator;
    public function paginateAgentOrdersByEvent(string $eventId, int $perPage, ?string $search): LengthAwarePaginator;
    public function agentsSummaryByEvent(string $eventId): array;
    public function findByOrderNumber(string $orderNumber): ?Order;
    public function create(array $data): Order;
    public function update(Order $order, array $data): Order;
    public function agentSummary(int $agentId, ?string $from, ?string $to): array;
}
