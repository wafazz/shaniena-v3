<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AboutUs;
use App\Models\Policy;
use App\Models\TermsConditions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The three single-row storefront pages.
 *
 * The source passed the table name in from the request and interpolated it
 * straight into SQL. Here the page is chosen from a fixed map, so the table
 * can never be caller-supplied.
 */
class PageContentController extends Controller
{
    private const PAGES = [
        'policy' => ['model' => Policy::class, 'title' => 'Policy', 'slug' => 'setting-policy'],
        'terms' => ['model' => TermsConditions::class, 'title' => 'Terms & Conditions', 'slug' => 'setting-terms'],
        'about-us' => ['model' => AboutUs::class, 'title' => 'About Us', 'slug' => 'setting-about-us'],
    ];

    public function edit(string $page): Response
    {
        abort_unless(isset(self::PAGES[$page]), 404);

        $meta = self::PAGES[$page];
        $model = $meta['model'];

        return Inertia::render('Admin/Settings/PageContent', [
            'page' => $page,
            'title' => $meta['title'],
            'slug' => $meta['slug'],
            'description' => $model::content()->description,
        ]);
    }

    public function update(Request $request, string $page): RedirectResponse
    {
        abort_unless(isset(self::PAGES[$page]), 404);

        $data = $request->validate(['description' => ['required', 'string']]);
        $model = self::PAGES[$page]['model'];

        $model::content()->update($data);

        return back()->with('success', self::PAGES[$page]['title'].' saved.');
    }
}
