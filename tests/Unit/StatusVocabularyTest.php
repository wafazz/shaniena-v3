<?php

use App\Models\Order;

/**
 * The order status vocabulary lives in two places by necessity — PHP for the
 * server, JS for the pill — so it is worth a test that they say the same thing.
 * The source had three different label sets for the same seven codes, which is
 * how "Awaiting Payment" and "Failed Payment" ended up naming one bucket.
 */
function pillLabels(): array
{
    $source = file_get_contents(resource_path('js/Components/StatusPill.vue'));

    preg_match_all(
        '/^\s*(\d+):\s*\{\s*label:\s*\'([^\']+)\'/m',
        $source,
        $matches,
        PREG_SET_ORDER,
    );

    return collect($matches)->mapWithKeys(fn ($m) => [(int) $m[1] => $m[2]])->all();
}

it('labels every status the same way on the server and in the browser', function () {
    $pill = pillLabels();

    expect($pill)->not->toBeEmpty()
        ->and(array_keys($pill))->toEqualCanonicalizing(array_keys(Order::STATUSES));

    foreach (Order::STATUSES as $code => $label) {
        expect($pill[$code] ?? null)->toBe($label, "Status {$code} is labelled differently in StatusPill.vue");
    }
});

it('keeps every transition target inside the vocabulary', function () {
    foreach (Order::ALLOWED_TRANSITIONS as $from => $targets) {
        expect(Order::STATUSES)->toHaveKey($from);

        foreach ($targets as $to) {
            expect(Order::STATUSES)->toHaveKey($to);

            // An operator must never be able to move an order back to a
            // payment state from the queue.
            expect($to)->not->toBe(Order::STATUS_AWAITING_PAYMENT)
                ->and($to)->not->toBe(Order::STATUS_DRAFT);
        }
    }
});
