<?php

namespace App\Services;

use App\Exceptions\IdempotentRequestInFlight;
use App\Models\IdempotencyKey;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Makes a create operation safe to repeat. Callers that resubmit a form — a double tap, a retry
 * after a timeout, a lost response — send the same key twice and get one resource back.
 *
 * The decision is made by the unique index on the table, not by a read-then-write in PHP, because
 * two simultaneous requests would both pass a read check before either had written anything.
 */
class IdempotencyGuard
{
    /**
     * Claim the key for this caller.
     *
     * @return int|null The id an earlier identical request already created, or null when this
     *                  caller won the claim and should create the resource itself.
     *
     * @throws IdempotentRequestInFlight when an identical request holds the claim and is not done.
     */
    public function claim(string $scope, int $userId, string $key): ?int
    {
        try {
            IdempotencyKey::create(['scope' => $scope, 'user_id' => $userId, 'key' => $key]);

            return null;
        } catch (UniqueConstraintViolationException) {
            $claim = $this->find($scope, $userId, $key);
            if ($claim?->resource_id === null) {
                throw new IdempotentRequestInFlight;
            }

            return $claim->resource_id;
        }
    }

    public function complete(string $scope, int $userId, string $key, int $resourceId): void
    {
        $this->find($scope, $userId, $key)?->update(['resource_id' => $resourceId]);
    }

    /** Hand the key back when creation failed, so the caller can genuinely retry with it. */
    public function release(string $scope, int $userId, string $key): void
    {
        $this->find($scope, $userId, $key)?->delete();
    }

    private function find(string $scope, int $userId, string $key): ?IdempotencyKey
    {
        return IdempotencyKey::where('scope', $scope)->where('user_id', $userId)->where('key', $key)->first();
    }
}
