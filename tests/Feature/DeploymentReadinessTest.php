<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

/**
 * Standing guards for Phase 9.
 *
 * A deployment breaks in ways a feature test never sees: a closure lands in a
 * config file and `config:cache` starts failing, someone adds an env knob and
 * documents it nowhere, a script in deploy/ picks up a syntax error nobody
 * runs until the night of a release. Each test here fails at that moment
 * rather than on the server.
 *
 * The two preflight tests need a database in a known state — the point of the
 * command is that it reports what is missing — so this file refreshes it.
 */
uses(RefreshDatabase::class);

/** The env() keys read by the config files this migration added. */
function ownConfigEnvKeys(): array
{
    $keys = [];

    foreach (['shop', 'shipping'] as $file) {
        preg_match_all(
            "/env\(\s*'([A-Z0-9_]+)'/",
            (string) file_get_contents(config_path("{$file}.php")),
            $matches,
        );

        $keys = array_merge($keys, $matches[1]);
    }

    sort($keys);

    return array_values(array_unique($keys));
}

function envTemplateKeys(string $file): array
{
    preg_match_all('/^([A-Z0-9_]+)=/m', (string) file_get_contents(base_path($file)), $matches);

    return $matches[1];
}

test('no config value is a closure, so config:cache cannot fail on the server', function () {
    $offenders = [];

    foreach (glob(config_path('*.php')) as $file) {
        $values = require $file;

        array_walk_recursive($values, function ($value, $key) use (&$offenders, $file) {
            if ($value instanceof Closure) {
                $offenders[] = basename($file).": {$key}";
            }
        });
    }

    // A closure anywhere under config/ makes `php artisan config:cache` throw,
    // and production without a config cache reparses every file on every
    // request.
    expect($offenders)->toBe([]);
});

test('every route this application defines is a controller action, so route:cache cannot fail', function () {
    $offenders = [];

    foreach (Route::getRoutes() as $route) {
        $action = $route->getAction('uses');

        if (! $action instanceof Closure) {
            continue;
        }

        // The framework registers a few of its own (the /up health check and
        // the local storage server). Those are the framework's problem; ours
        // are the ones declared in routes/.
        $file = (new ReflectionFunction($action))->getFileName();

        if (! str_starts_with((string) $file, base_path('vendor'))) {
            $offenders[] = $route->uri().' ('.$file.')';
        }
    }

    expect($offenders)->toBe([]);
});

test('every env knob this migration added is documented in both templates', function () {
    $documented = envTemplateKeys('.env.example');
    $production = envTemplateKeys('.env.production.example');

    $keys = ownConfigEnvKeys();

    // toContain() is variadic, so a failure names every key rather than the
    // first one — which is what you want when a whole config file was added.
    expect(array_values(array_diff($keys, $documented)))->toBe([], 'read by config/, absent from .env.example');
    expect(array_values(array_diff($keys, $production)))->toBe([], 'read by config/, absent from .env.production.example');

    expect($keys)->not->toBeEmpty();
});

test('the production template is safe to copy onto a server as it stands', function () {
    $template = (string) file_get_contents(base_path('.env.production.example'));

    $required = [
        'APP_ENV=production',
        'APP_DEBUG=false',
        'SESSION_SECURE_COOKIE=true',
        'SESSION_HTTP_ONLY=true',
        // Gateways return the customer by a cross-site POST; `strict` drops
        // the session on the way back.
        'SESSION_SAME_SITE=lax',
    ];

    foreach ($required as $line) {
        expect($template)->toContain($line);
    }

    // Nothing secret may ship with a value. The template is committed; the
    // filled-in file lives only on the server, and .gitignore keeps
    // `.env.production` itself out of the repository.
    foreach (['APP_KEY', 'DB_PASSWORD', 'REDIS_PASSWORD', 'MAIL_PASSWORD', 'NINJAVAN_CLIENT_SECRET', 'JT_TRACKING_KEY', 'SRC_DB_PASSWORD'] as $secret) {
        expect($template)->toMatch("/^{$secret}=\\s*(#.*)?$/m", "{$secret} has a value in the committed template");
    }

    expect(file_get_contents(base_path('.gitignore')))->toContain('.env.production');
});

test('the deploy scripts exist, are executable and parse', function (string $script) {
    $path = base_path($script);

    expect(is_file($path))->toBeTrue("{$script} is missing");
    expect(is_executable($path))->toBeTrue("{$script} is not executable — it would fail on the server");

    exec('bash -n '.escapeshellarg($path).' 2>&1', $output, $status);

    expect($status)->toBe(0, "{$script} has a syntax error: ".implode("\n", $output));
})->with(['deploy/deploy.sh', 'deploy/rollback.sh']);

test('the runbook ships with the files it tells an operator to install', function () {
    $files = [
        'deploy/nginx/shaniena.conf',
        'deploy/supervisor/shaniena-worker.conf',
        'deploy/supervisor/shaniena-ssr.conf',
        'deploy/crontab',
        '.env.production.example',
        'docs/deployment.md',
        'docs/cutover.md',
    ];

    foreach ($files as $file) {
        expect(is_file(base_path($file)))->toBeTrue("{$file} is referenced by the runbook but not in the repository");
    }

    // The supervised commands must match what production is configured to be,
    // which is the template's value and not this test run's (`sync` here).
    preg_match('/^QUEUE_CONNECTION=(\w+)/m', (string) file_get_contents(base_path('.env.production.example')), $queue);

    expect(file_get_contents(base_path('deploy/supervisor/shaniena-worker.conf')))
        ->toContain('queue:work '.$queue[1]);

    expect(file_get_contents(base_path('deploy/supervisor/shaniena-ssr.conf')))
        ->toContain('bootstrap/ssr/'.basename(config('inertia.ssr.bundle')));
});

test('preflight fails when the host cannot serve the shop', function () {
    Http::fake(['*' => Http::response('', 500)]);

    $this->artisan('shaniena:preflight')
        ->expectsOutputToContain('ReferenceDataSeeder')
        ->assertFailed();
});

test('preflight passes on a host that can', function () {
    // The SSR renderer answers /health when it is up.
    Http::fake(['*' => Http::response(['status' => 'OK'], 200)]);

    config(['mail.from.address' => 'orders@shaniena.test']);

    shopCountry();
    DB::table('state_my')->insert(['state_code' => 'SGR', 'state_name' => 'Selangor']);
    DB::table('postcode_my')->insert(['postcode' => '43800', 'post_office' => 'Dengkil', 'state_code' => 'SGR']);

    // `assets` is skipped on purpose: public/build is a build artefact and is
    // not committed, so a checkout that has not run `npm run build` would fail
    // this for the right reason at the wrong time. deploy.sh builds before it
    // runs preflight, and the check is what catches a build that silently
    // produced nothing.
    $this->artisan('shaniena:preflight', ['--skip' => ['assets']])
        ->assertSuccessful();
});
