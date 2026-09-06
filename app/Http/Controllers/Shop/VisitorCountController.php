<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Storefront\Visitors;
use Illuminate\Http\JsonResponse;

/**
 * The live visitor figures the storefront card polls.
 *
 * Aggregates only — never an address, a session or a page. Served from the
 * cache the scheduled job refreshes once a minute, so a thousand shoppers
 * polling costs a thousand cache reads and no queries.
 */
class VisitorCountController extends Controller
{
    public function __invoke(Visitors $visitors): JsonResponse
    {
        return response()->json($visitors->counts());
    }
}
