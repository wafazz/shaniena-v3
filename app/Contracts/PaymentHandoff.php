<?php

namespace App\Contracts;

/** Where to send the customer to pay. */
final class PaymentHandoff
{
    /**
     * @param  array<string, scalar>  $fields  POSTed to $url when $method is POST
     */
    private function __construct(
        public readonly string $url,
        public readonly string $method,
        public readonly array $fields,
    ) {}

    public static function redirect(string $url): self
    {
        return new self($url, 'GET', []);
    }

    /** @param array<string, scalar> $fields */
    public static function post(string $url, array $fields): self
    {
        return new self($url, 'POST', $fields);
    }
}
