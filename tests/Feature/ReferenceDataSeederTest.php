<?php

use App\Models\AllCountry;
use App\Models\ListCountry;
use App\Models\PostcodeMy;
use App\Models\StateMy;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('seeds every reference table from the committed data files', function () {
    $this->seed(ReferenceDataSeeder::class);

    expect(ListCountry::count())->toBe(5)
        ->and(AllCountry::count())->toBe(352)
        ->and(StateMy::count())->toBe(16)
        ->and(PostcodeMy::count())->toBe(56234);
});

it('preserves the selling countries exactly as the source recorded them', function () {
    $this->seed(ReferenceDataSeeder::class);

    $malaysia = ListCountry::find(ListCountry::MALAYSIA_ID);

    expect($malaysia->name)->toBe('Malaysia')
        ->and($malaysia->sign)->toBe('MYR')
        ->and((float) $malaysia->rate)->toBe(1.0)
        ->and($malaysia->phone_code)->toBe('+60')
        ->and($malaysia->isActive())->toBeTrue();

    // Only Malaysia is switched on in the source data.
    expect(ListCountry::active()->pluck('name')->all())->toBe(['Malaysia']);
});

it('covers every postcode with a known state', function () {
    $this->seed(ReferenceDataSeeder::class);

    $orphans = DB::table('postcode_my as p')
        ->leftJoin('state_my as s', 'p.state_code', '=', 's.state_code')
        ->whereNull('s.state_code')
        ->count();

    expect($orphans)->toBe(0);
});

it('resolves a postcode to its area and state', function () {
    $this->seed(ReferenceDataSeeder::class);

    $row = PostcodeMy::forPostcode('43800')->first();

    expect($row)->not->toBeNull()
        ->and($row->state_code)->toBe('SGR')
        ->and($row->state->state_name)->toBe('Selangor');
});

it('is idempotent — a second run changes no counts', function () {
    $this->seed(ReferenceDataSeeder::class);
    $before = [ListCountry::count(), AllCountry::count(), StateMy::count(), PostcodeMy::count()];

    $this->seed(ReferenceDataSeeder::class);

    expect([ListCountry::count(), AllCountry::count(), StateMy::count(), PostcodeMy::count()])
        ->toBe($before);
});

it('does not seed the state table, whose shipping_zone drives pricing', function () {
    $this->seed(ReferenceDataSeeder::class);

    // Guards plan item 2.15: zones are money-affecting config and must come
    // from live data, never from a seeder's assumption.
    expect(DB::table('state')->count())->toBe(0);
});
