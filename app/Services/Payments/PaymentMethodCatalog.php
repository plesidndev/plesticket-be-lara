<?php

namespace App\Services\Payments;

use App\Services\Payments\Data\PaymentMethod;
use Illuminate\Support\Collection;

/**
 * Reads the config/payments.php catalog and hands back typed PaymentMethod
 * objects. The single place that knows the catalog's storage format — moving
 * it to a database table later means changing only this class.
 */
class PaymentMethodCatalog
{
    /** @return Collection<int, PaymentMethod> */
    public function all(): Collection
    {
        return collect(config('payments.methods', []))
            ->map(fn(array $method) => PaymentMethod::fromConfig($method));
    }

    /** @return Collection<int, PaymentMethod> */
    public function enabled(): Collection
    {
        return $this->all()->filter(fn(PaymentMethod $m) => $m->enabled)->values();
    }

    public function find(string $code): ?PaymentMethod
    {
        return $this->all()->firstWhere('code', $code);
    }

    /**
     * Enabled methods grouped by type, for a checkout picker that shows
     * "Virtual Account" with BRI and Mandiri nested under it.
     *
     * @return Collection<int, array{type: string, label: string, methods: Collection<int, PaymentMethod>}>
     */
    public function groupedByType(): Collection
    {
        return $this->enabled()
            ->groupBy(fn(PaymentMethod $m) => $m->type->value)
            ->map(fn(Collection $methods, string $type) => [
                'type'    => $type,
                'label'   => $methods->first()->type->label(),
                'methods' => $methods->values(),
            ])
            ->values();
    }
}
