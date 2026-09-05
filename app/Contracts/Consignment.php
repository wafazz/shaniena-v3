<?php

namespace App\Contracts;

/** A booked shipment. */
final class Consignment
{
    public function __construct(
        public readonly string $awb,
        public readonly string $trackingUrl,
        public readonly string $courier,
    ) {}
}
