<?php

namespace App\Services\Shipping;

use App\Contracts\ShippingGateway;
use Illuminate\Support\Collection;

/** The couriers the store can book with. */
class Couriers
{
    /** @param list<ShippingGateway> $couriers */
    public function __construct(private array $couriers) {}

    /**
     * Matched case-insensitively. The source dispatched on an exact
     * 'J&T Express' but wrote back 'J&T EXPRESS', so re-dispatching an already
     * shipped order fell into the "no courier assigned" branch.
     */
    public function for(?string $name): ?ShippingGateway
    {
        if (blank($name)) {
            return null;
        }

        return collect($this->couriers)
            ->first(fn (ShippingGateway $c) => strcasecmp($c->name(), $name) === 0);
    }

    /** @return Collection<int, ShippingGateway> */
    public function configured(): Collection
    {
        return collect($this->couriers)->filter(fn (ShippingGateway $c) => $c->isConfigured())->values();
    }

    /** @return list<string> */
    public function names(): array
    {
        return collect($this->couriers)->map(fn (ShippingGateway $c) => $c->name())->all();
    }
}
