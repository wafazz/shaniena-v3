<?php

use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Standing guards for Phase 7.
 *
 * These are less tests of behaviour than tests of the shape of the code: each
 * fails the moment somebody adds a route, model or upload that skips a control
 * the rest of the application applies.
 */

/** Every file of one extension under a directory, however deep. */
function sourcesIn(string $directory, string $extension): array
{
    $found = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === $extension) {
            $found[] = $file->getPathname();
        }
    }

    sort($found);

    return $found;
}

function phpSources(string $directory): array
{
    return sourcesIn($directory, 'php');
}

// ---------------------------------------------------------------------------
// 7.4 Authorization on every admin route
// ---------------------------------------------------------------------------

/**
 * Admin routes that legitimately carry no page grant: the auth screens, which
 * a signed-out person has to reach, and the self-service pages every signed-in
 * member of staff owns. Anything else must name a page.
 */
const UNGUARDED_ADMIN_ROUTES = [
    'admin.login', 'admin.login.store', 'admin.logout',
    'admin.password.request', 'admin.password.email',
    'admin.password.reset', 'admin.password.update',
    'admin.password', 'admin.password.change',
    'admin.profile', 'admin.profile.update',
];

function adminRoutes(): Collection
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'admin'));
}

it('puts a page grant on every admin route that is not auth or self-service', function () {
    $unguarded = adminRoutes()
        ->reject(fn ($route) => collect($route->gatherMiddleware())
            ->contains(fn ($m) => is_string($m) && str_starts_with($m, 'page:')))
        ->map(fn ($route) => $route->getName() ?: $route->uri())
        ->values();

    expect($unguarded->all())->toEqualCanonicalizing(UNGUARDED_ADMIN_ROUTES);
});

it('gives every admin route a name, and no two the same', function () {
    $names = adminRoutes()->map(fn ($route) => $route->getName())->filter()->values();

    // Two routes sharing a name means route() silently resolves to whichever
    // registered last, and the other becomes unreachable by name.
    expect($names->duplicates()->all())->toBeEmpty();
});

it('puts every admin route behind the admin guard', function () {
    $open = adminRoutes()
        ->reject(fn ($route) => collect($route->gatherMiddleware())
            ->contains(fn ($m) => is_string($m) && ($m === 'auth:admin' || $m === 'guest:admin')))
        ->map(fn ($route) => $route->getName() ?: $route->uri())
        ->values();

    expect($open->all())->toBeEmpty();
});

// ---------------------------------------------------------------------------
// 7.3 Mass-assignment guards
// ---------------------------------------------------------------------------

it('gives every model an allowlist rather than an open guard', function () {
    $offenders = [];

    foreach (glob(app_path('Models/*.php')) as $file) {
        $class = 'App\\Models\\'.basename($file, '.php');

        if (! class_exists($class) || (new ReflectionClass($class))->isAbstract()) {
            continue;
        }

        $model = new $class;

        // Either an explicit fillable allowlist, or fully guarded. A partial
        // $guarded denylist is what lets a new column arrive unprotected.
        if ($model->getGuarded() === [] || ($model->getFillable() === [] && $model->getGuarded() !== ['*'])) {
            $offenders[] = class_basename($class);
        }
    }

    expect($offenders)->toBeEmpty();
});

it('keeps what an order is worth out of mass assignment', function () {
    $fillable = (new Order)->getFillable();

    // Status, the money columns and the courier fields are what an attacker
    // would want to set. Every legitimate writer uses forceFill.
    foreach (Order::PROTECTED_COLUMNS as $column) {
        expect($fillable)->not->toContain($column);
    }

    expect((new Order)->getGuarded())->toBe(['*']);
});

// ---------------------------------------------------------------------------
// 7.2 CSRF
// ---------------------------------------------------------------------------

it('exempts only gateway callbacks from CSRF', function () {
    preg_match(
        '/validateCsrfTokens\(except:\s*\[(.*?)\]/s',
        file_get_contents(base_path('bootstrap/app.php')),
        $block,
    );

    expect($block)->not->toBeEmpty();

    preg_match_all("/'([^']+)'/", $block[1], $paths);

    // Server-to-server posts with no session, each authenticated by its own
    // signature. Nothing else may skip the token.
    expect($paths[1])->toBe(['payment/callback/*']);
});

// ---------------------------------------------------------------------------
// 7.6 Rate limiting
// ---------------------------------------------------------------------------

it('rate limits everything a stranger can reach', function () {
    $limited = [
        'shop.checkout.address', 'shop.pay', 'shop.pay.callback',
        'shop.support.store', 'shop.support.reply',
        'shop.register.store', 'shop.verify.store', 'shop.verify.resend',
        'shop.track',
        'admin.password.email', 'admin.password.update',
    ];

    foreach ($limited as $name) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)->not->toBeNull("Route {$name} has gone missing");

        expect(collect($route->gatherMiddleware())
            ->contains(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:')))
            ->toBeTrue("Route {$name} has no rate limit");
    }
});

// ---------------------------------------------------------------------------
// 7.5 File uploads
// ---------------------------------------------------------------------------

it('never accepts an SVG upload', function () {
    $offenders = [];

    foreach (phpSources(app_path('Http')) as $file) {
        // Rule strings only: a comment explaining why SVG is refused must not
        // itself read as an SVG rule.
        preg_match_all('/[\'"](?:mimes|mimetypes|image):[^\'"]*[\'"]/i', file_get_contents($file), $rules);

        foreach ($rules[0] as $rule) {
            if (preg_match('/\bsvg\b|allow_svg/i', $rule)) {
                $offenders[] = basename($file);
            }
        }
    }

    // SVG is XML, it can carry a <script>, and uploads are served from the
    // storefront's own origin — that is stored XSS.
    expect($offenders)->toBeEmpty();
});

it('bounds and types every upload it accepts', function () {
    $offenders = [];

    foreach (phpSources(app_path('Http/Controllers')) as $file) {
        $body = file_get_contents($file);

        if (! str_contains($body, '->file(')) {
            continue;
        }

        // The rules may live in the controller or in the FormRequest it
        // type-hints, so both count.
        $rules = $body;

        preg_match_all('/use (App\\\\Http\\\\Requests\\\\[A-Za-z\\\\]+);/', $body, $requests);

        foreach ($requests[1] as $class) {
            $path = base_path(str_replace(['App\\', '\\'], ['app/', '/'], $class).'.php');

            if (is_file($path)) {
                $rules .= "\n".file_get_contents($path);
            }
        }

        if (! str_contains($rules, 'mimes:') || ! preg_match('/\bmax:\d+/', $rules)) {
            $offenders[] = basename($file);
        }
    }

    expect($offenders)->toBeEmpty();
});

// ---------------------------------------------------------------------------
// 7.7 / 7.8 Injection
// ---------------------------------------------------------------------------

it('interpolates nothing into raw SQL', function () {
    $offenders = [];

    foreach (phpSources(app_path()) as $file) {
        foreach (file($file) as $number => $line) {
            $built = preg_match(
                '/(whereRaw|orderByRaw|selectRaw|havingRaw|DB::raw|DB::statement|DB::select)\s*\(\s*[\'"][^\'"]*[\'"]\s*\./',
                $line,
            );

            // Building a fragment is fine when what it builds is placeholders
            // and the values travel separately as bindings.
            $bound = str_contains($line, '?') && preg_match('/,\s*\$/', $line);

            if ($built && ! $bound) {
                $offenders[] = basename($file).':'.($number + 1);
            }
        }
    }

    expect($offenders)->toBeEmpty();
});

it('renders no untrusted HTML', function () {
    $offenders = [];

    foreach (sourcesIn(resource_path('js'), 'vue') as $file) {
        if (str_contains(file_get_contents($file), 'v-html')) {
            $offenders[] = basename($file);
        }
    }

    // The source echoed database content straight into markup in several
    // places; nothing here does.
    expect($offenders)->toBeEmpty();
});

// ---------------------------------------------------------------------------
// 7.1 Credentials
// ---------------------------------------------------------------------------

it('reads every credential from the environment', function () {
    $offenders = [];

    $patterns = [
        '/[\'"]sk_live_[A-Za-z0-9]+[\'"]/',
        '/[\'"]xkeysib-[A-Za-z0-9]+[\'"]/',
        '/-----BEGIN [A-Z ]*PRIVATE KEY/',
        // A quoted literal assigned to a secret-ish key, long enough to be a
        // real credential rather than a placeholder.
        '/[\'"](?:secret_key|api_key|password|client_secret)[\'"]\s*=>\s*[\'"][^\'"$]{12,}[\'"]/i',
    ];

    foreach (array_merge(phpSources(app_path()), glob(config_path('*.php'))) as $file) {
        $body = file_get_contents($file);

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $body)) {
                $offenders[] = basename($file);
            }
        }
    }

    // The source carried the production DB password, J&T's signing key and
    // Billplz API keys in tracked files.
    expect(array_values(array_unique($offenders)))->toBeEmpty();
});
