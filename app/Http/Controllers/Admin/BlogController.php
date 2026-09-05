<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsBlog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Announcement & Blog.
 *
 * Only the blog half is here: the `announcement` table has no DDL anywhere in
 * the source (see the plan's Schema Gap), so that feature stays blocked.
 */
class BlogController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Content/Blog', [
            'posts' => NewsBlog::query()
                ->with('author:id,f_name,l_name')
                ->latest('id')
                ->paginate(20)
                ->through(fn (NewsBlog $post) => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'author' => $post->author ? trim("{$post->author->f_name} {$post->author->l_name}") : 'Unknown',
                    'readers' => (int) $post->reader,
                    'published_at' => $post->created_at?->format('j M Y, h:iA'),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:1500'],
            'contents' => ['required', 'string'],
        ]);

        $post = NewsBlog::create($data + [
            'post_by' => (int) $request->user('admin')->getKey(),
            'update_by' => '',
            'reader' => '0',
        ]);

        return back()->with('success', "\"{$post->title}\" published.");
    }

    public function update(Request $request, NewsBlog $post): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:1500'],
            'contents' => ['required', 'string'],
        ]);

        $post->update($data + ['update_by' => (string) $request->user('admin')->full_name]);

        return back()->with('success', 'Post updated.');
    }

    public function destroy(NewsBlog $post): RedirectResponse
    {
        $title = $post->title;
        $post->delete();

        return back()->with('success', "\"{$title}\" removed.");
    }
}
