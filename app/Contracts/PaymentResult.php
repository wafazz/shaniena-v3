<?php

namespace App\Contracts;

/** What a verified callback means. */
final class PaymentResult
{
    private function __construct(
        public readonly bool $verified,
        public readonly bool $paid,
        public readonly ?string $reference,
        public readonly string $paymentCode,
        public readonly string $message,
    ) {}

    public static function paid(string $reference, string $paymentCode = ''): self
    {
        return new self(true, true, $reference, $paymentCode, 'Payment received.');
    }

    public static function declined(string $reference, string $message = 'Payment was not completed.'): self
    {
        return new self(true, false, $reference, '', $message);
    }

    /**
     * The signature did not check out. Nothing in the request may be trusted —
     * not the order reference, not the amount, not the status.
     */
    public static function untrusted(string $message = 'Could not verify that callback.'): self
    {
        return new self(false, false, null, '', $message);
    }
}
