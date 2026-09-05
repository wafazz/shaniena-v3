<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serves every icon the manifest promises', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

    expect($manifest['icons'])->not->toBeEmpty();

    foreach ($manifest['icons'] as $icon) {
        $path = public_path(ltrim($icon['src'], '/'));

        // Chrome will not offer to install a site whose manifest points at an
        // icon that 404s — and all three of these were missing.
        expect(is_file($path))->toBeTrue("Missing icon: {$icon['src']}");

        [$width, $height] = getimagesize($path);
        [$declaredW, $declaredH] = array_map('intval', explode('x', $icon['sizes']));

        expect($width)->toBe($declaredW)
            ->and($height)->toBe($declaredH);
    }
});

it('ships a maskable icon as well as a plain one', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);
    $purposes = array_column($manifest['icons'], 'purpose');

    // Without one, Android crops the square mark into its own shape and takes
    // a bite out of the logo.
    expect($purposes)->toContain('maskable')->toContain('any');
});

it('serves the icons the storefront head links to', function () {
    $head = file_get_contents(resource_path('views/storefront.blade.php'));

    preg_match_all('/href="(\/storefront\/img\/[^"]+)"/', $head, $matches);

    expect($matches[1])->not->toBeEmpty();

    foreach ($matches[1] as $src) {
        expect(is_file(public_path(ltrim($src, '/'))))->toBeTrue("Missing: {$src}");
    }
});

it('ships the service worker, the offline page and the manifest', function () {
    // Served straight off disk by the web server, so this checks the files
    // rather than routing to them.
    foreach (['sw.js', 'offline.html', 'manifest.webmanifest'] as $file) {
        expect(is_file(public_path($file)))->toBeTrue("Missing: {$file}");
    }

    $worker = file_get_contents(public_path('sw.js'));

    // Product images are deliberately not cache-first: the source cached them
    // that way, so a re-uploaded photo never reached anyone who had seen the
    // old one.
    expect($worker)->toContain('offline.html');
});
