<?php

/**
 * The source wrote every timestamp in Asia/Kuala_Lumpur via dateNow()
 * (config/function.php:1400). Laravel's stock config/app.php hardcodes 'UTC'
 * and ignores APP_TIMEZONE, which would have written every migrated datetime
 * 8 hours off. This locks the fix in.
 */
it('runs on the Malaysian timezone the source wrote its datetimes in', function () {
    expect(config('app.timezone'))->toBe('Asia/Kuala_Lumpur')
        ->and(date_default_timezone_get())->toBe('Asia/Kuala_Lumpur');
});

it('produces Carbon instances in that timezone', function () {
    expect(now()->getTimezone()->getName())->toBe('Asia/Kuala_Lumpur')
        ->and(now()->utcOffset())->toBe(480);
});
