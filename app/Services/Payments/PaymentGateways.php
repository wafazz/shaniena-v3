<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use Illuminate\Support\Collection;

/** The registry of payment channels the store can offer. */
class PaymentGateways
{
    /** @param list<PaymentGateway> $gateways */
    public function __construct(private array $gateways) {}

    public function get(string $channel): ?PaymentGateway
    {
        return collect($this->gateways)->first(fn (PaymentGateway $g) => $g->channel() === $channel);
    }

    /** Only the channels that are switched on AND configured. */
    public function available(): Collection
    {
        return collect($this->gateways)->filter(fn (PaymentGateway $g) => $g->isAvailable())->values();
    }

    /** @return array<string, bool> */
    public function availability(): array
    {
        return collect($this->gateways)
            ->mapWithKeys(fn (PaymentGateway $g) => [$g->channel() => $g->isAvailable()])
            ->all();
    }
}
