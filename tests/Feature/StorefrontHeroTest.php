<?php

use App\Models\Slider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function slide(string $title, int $sort, bool $status = true, ?string $link = null): Slider
{
    return Slider::create([
        'title' => $title,
        'link_url' => $link,
        'image' => 'sliders/'.str($title)->slug().'.webp',
        'sort_order' => $sort,
        'status' => $status,
    ]);
}

it('puts the slider the console manages on the homepage', function () {
    // The source's hero was three hardcoded .webp files inside a block that
    // was commented out, so this table was never read by anything.
    slide('Third', 3);
    slide('First', 1);
    slide('Second', 2);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('slides', 3)
            ->where('slides.0.title', 'First')
            ->where('slides.1.title', 'Second')
            ->where('slides.2.title', 'Third'));
});

it('leaves hidden slides off the homepage', function () {
    slide('Live', 1);
    slide('Draft', 2, status: false);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('slides', 1)
            ->where('slides.0.title', 'Live'));
});

it('marks only the first slide for eager loading', function () {
    slide('One', 1);
    slide('Two', 2);
    slide('Three', 3);

    // The first slide is the largest thing above the fold; the rest must not
    // compete with it for bandwidth on load.
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('slides.0.first', true)
            ->where('slides.1.first', false)
            ->where('slides.2.first', false));
});

it('resolves slide images to a public URL', function () {
    slide('Raya', 1, link: '/categories/skincare');

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('slides.0.image', fn ($url) => str_contains($url, '/storage/sliders/raya.webp'))
            ->where('slides.0.link', '/categories/skincare'));
});

it('renders no hero band at all when nothing is scheduled', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('slides', 0));
});

it('gives a slide with no link no anchor to follow', function () {
    slide('Just a picture', 1);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('slides.0.link', null));
});
