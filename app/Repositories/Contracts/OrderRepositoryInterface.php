<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use Illuminate\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface
{
    public function paginateByBuyer(int $buyerId, int $perPage): LengthAwarePaginator;
    public function findByOrderNumber(string $orderNumber): ?Order;
    public function create(array $data): Order;
    public function update(Order $order, array $data): Order;
}
