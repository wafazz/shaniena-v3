<?php

use App\Models\CodCharge;
use App\Models\PostageCost;

/**
 * Pricing arithmetic ported from calculatePostage() (config/function.php:71)
 * and the COD benchmark block in view/ecom/e-checkout-keya88.php:198-206.
 * These run without the database — the models are only value holders here.
 */
function postage(float $first, float $next): PostageCost
{
    return new PostageCost(['first_kilo' => $first, 'next_kilo' => $next]);
}

it('charges only the first kilo at or below 1kg', function () {
    expect(postage(6.50, 3.00)->costForWeight(0.2))->toBe(6.50)
        ->and(postage(6.50, 3.00)->costForWeight(1.0))->toBe(6.50);
});

it('rounds extra weight up to the next whole kilo', function () {
    // 1.1kg -> 0.1kg extra -> ceil to 1 extra kilo.
    expect(postage(6.50, 3.00)->costForWeight(1.1))->toBe(9.50)
        ->and(postage(6.50, 3.00)->costForWeight(2.0))->toBe(9.50)
        ->and(postage(6.50, 3.00)->costForWeight(2.01))->toBe(12.50)
        ->and(postage(6.50, 3.00)->costForWeight(3.0))->toBe(12.50);
});

it('treats a zero weight as the base rate', function () {
    expect(postage(6.50, 3.00)->costForWeight(0))->toBe(6.50);
});

function codCharge(float $benchmark, float $below, float $above): CodCharge
{
    return new CodCharge([
        'benchmark_amount' => $benchmark,
        'cod_fee_below' => $below,
        'cod_fee_above' => $above,
    ]);
}

it('charges the below-benchmark fee under the threshold', function () {
    expect(codCharge(100, 10, 8)->feeForSubtotal(99.99))->toBe(10.0);
});

it('charges the above-benchmark fee at exactly the threshold', function () {
    // The source comparison is `$subTotals < benchmark`, so the boundary
    // itself pays the above-benchmark fee.
    expect(codCharge(100, 10, 8)->feeForSubtotal(100.00))->toBe(8.0)
        ->and(codCharge(100, 10, 8)->feeForSubtotal(250.00))->toBe(8.0);
});
