<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\AboutUs;
use App\Models\NewsBlog;
use App\Models\Policy;
use App\Models\TermsConditions;
use App\Services\StoreSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ContentController extends Controller
{
    private const PAGES = [
        'about' => [AboutUs::class, 'About us'],
        'policy' => [Policy::class, 'Policy'],
        'terms' => [TermsConditions::class, 'Terms & conditions'],
    ];

    public function page(string $page): Response
    {
        abort_unless(isset(self::PAGES[$page]), 404);

        [$model, $title] = self::PAGES[$page];

        return Inertia::render('Shop/Page', [
            'title' => $title,
            'body' => $model::content()->description,
        ]);
    }

    public function contact(StoreSettings $settings): Response
    {
        return Inertia::render('Shop/Contact', [
            'store' => [
                'name' => $settings->get('store_name', 'Shaniena'),
                'email' => $settings->get('store_email'),
                'phone' => $settings->get('store_phone'),
                'address' => $settings->get('store_address'),
                'whatsapp' => $settings->get('whatsapp_number'),
            ],
        ]);
    }

    public function blog(): Response
    {
        return Inertia::render('Shop/Blog', [
            'posts' => NewsBlog::query()
                ->with('author:id,f_name,l_name')
                ->latest('id')
                ->paginate(9)
                ->through(fn (NewsBlog $post) => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'excerpt' => Str::limit(strip_tags((string) $post->contents), 160),
                    'published_at' => $post->created_at?->format('j M Y'),
                ]),
        ]);
    }

    public function post(Request $request, NewsBlog $post): Response
    {
        // One view per IP, as the source counted them.
        $post->recordView((string) $request->ip());

        return Inertia::render('Shop/Post', [
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'contents' => $post->contents,
                'readers' => (int) $post->fresh()->reader,
                'published_at' => $post->created_at?->format('j M Y'),
            ],
        ]);
    }
}
