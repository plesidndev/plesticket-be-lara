<?php

namespace App\Services\Payments;

use App\Enums\PaymentProvider;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves a PaymentProvider to its gateway implementation using the
 * `providers` map in config/payments.php.
 */
class PaymentGatewayManager
{
    /** @var array<string, PaymentGatewayInterface> */
    private array $resolved = [];

    public function __construct(private readonly Container $container) {}

    public function for(PaymentProvider $provider): PaymentGatewayInterface
    {
        return $this->resolved[$provider->value] ??= $this->resolve($provider);
    }

    private function resolve(PaymentProvider $provider): PaymentGatewayInterface
    {
        $class = config("payments.providers.{$provider->value}");

        if (! $class) {
            throw new InvalidArgumentException(
                "No gateway configured for provider \"{$provider->value}\"."
            );
        }

        $gateway = $this->container->make($class);

        if (! $gateway instanceof PaymentGatewayInterface) {
            throw new InvalidArgumentException(
                "Gateway {$class} must implement PaymentGatewayInterface."
            );
        }

        return $gateway;
    }
}
