<?php

namespace App\Http\Middleware;

use App\Models\MemberHq;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards an admin route with its role_access slug:
 *
 *     Route::get('/admin/orders', ...)->middleware('page:orders');
 *
 * Phase 7.4 requires every admin route to carry one; the source relied on
 * ad-hoc roleVerify() calls inside views, which was easy to forget.
 */
class EnsurePageAccess
{
    public function handle(Request $request, Closure $next, string $slug): Response
    {
        $user = $request->user('admin');

        abort_unless($user instanceof MemberHq && $user->can('access', $slug), 403);

        return $next($request);
    }
}
