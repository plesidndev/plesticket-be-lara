<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * Raised when a provider rejects a request or is unreachable. Carries the
 * provider's own payload so the failure can be logged with full context.
 */
class PaymentGatewayException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
