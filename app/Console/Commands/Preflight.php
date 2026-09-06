<?php

namespace App\Console\Commands;

use App\Models\ListCountry;
use App\Models\PostcodeMy;
use App\Models\StateMy;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schedule;
use Throwable;

/**
 * Asks the host whether it can actually serve the shop.
 *
 * The source project had no such thing: a deploy was an FTP upload, and the
 * first sign that a config value had not been carried across was a customer
 * hitting a white page. Every check here is one of those failures written
 * down — a missing APP_KEY, a queue nobody is draining, an SSR process that
 * died with the release before it, a `public/storage` link that was never
 * made, reference tables that were never seeded.
 *
 * It exits non-zero on the first failing check set, so `deploy.sh` can run it
 * against a release before the `current` symlink moves and roll back on its
 * own if it fails.
 */
class Preflight extends Command
{
    protected $signature = 'shaniena:preflight
        {--skip=* : Check names to skip, e.g. --skip=ssr --skip=mail}';

    protected $description = 'Verify this host can serve the shop before traffic is pointed at it';

    /** Checks that failed, by name — non-empty means a non-zero exit. */
    private array $failed = [];

    public function handle(): int
    {
        $skip = array_map('strtolower', (array) $this->option('skip'));
        $rows = [];

        foreach ($this->checks() as $name => $check) {
            if (in_array($name, $skip, true)) {
                $rows[] = [$name, '<comment>skip</comment>', 'skipped with --skip'];

                continue;
            }

            try {
                [$passed, $detail] = $check();
            } catch (Throwable $e) {
                [$passed, $detail] = [false, $e->getMessage()];
            }

            if ($passed === null) {
                $rows[] = [$name, '<comment>n/a</comment>', $detail];

                continue;
            }

            if (! $passed) {
                $this->failed[] = $name;
            }

            $rows[] = [$name, $passed ? '<info>ok</info>' : '<error>FAIL</error>', $detail];
        }

        $this->newLine();
        $this->table(['Check', 'Result', 'Detail'], $rows);

        if ($this->failed !== []) {
            $this->newLine();
            $this->error(count($this->failed).' check(s) failed: '.implode(', ', $this->failed));

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('All checks passed — this host can take traffic.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, callable(): array{0: bool|null, 1: string}>
     */
    private function checks(): array
    {
        return [
            'app key' => fn () => $this->appKey(),
            'debug off' => fn () => $this->debugOff(),
            'app url' => fn () => $this->appUrl(),
            'database' => fn () => $this->database(),
            'migrations' => fn () => $this->migrations(),
            'cache store' => fn () => $this->cacheStore(),
            'queue' => fn () => $this->queue(),
            'scheduler' => fn () => $this->scheduler(),
            'assets' => fn () => $this->assets(),
            'ssr' => fn () => $this->ssr(),
            'storage link' => fn () => $this->storageLink(),
            'writable paths' => fn () => $this->writablePaths(),
            'framework caches' => fn () => $this->frameworkCaches(),
            'session cookie' => fn () => $this->sessionCookie(),
            'mail' => fn () => $this->mail(),
            'reference data' => fn () => $this->referenceData(),
        ];
    }

    private function appKey(): array
    {
        $key = (string) config('app.key');

        return [$key !== '', $key === '' ? 'APP_KEY is empty — every encrypted cookie and session would fail' : 'set'];
    }

    private function debugOff(): array
    {
        if (! app()->isProduction()) {
            return [null, 'APP_ENV='.app()->environment().' — only enforced in production'];
        }

        return [! config('app.debug'), config('app.debug')
            ? 'APP_DEBUG=true in production — stack traces would show credentials to customers'
            : 'APP_DEBUG=false'];
    }

    private function appUrl(): array
    {
        $url = (string) config('app.url');
        $host = parse_url($url, PHP_URL_HOST);

        if ($host === null || $host === false || $host === '') {
            return [false, "APP_URL is not an absolute URL: '{$url}'"];
        }

        if (! app()->isProduction()) {
            return [true, $url];
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return [false, "APP_URL still points at {$host} — mail links and the storage disk URL would be unreachable"];
        }

        if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return [false, "APP_URL is not https: '{$url}'"];
        }

        return [true, $url];
    }

    private function database(): array
    {
        $connection = DB::connection();
        $connection->getPdo();

        return [true, $connection->getName().' → '.$connection->getDatabaseName()];
    }

    private function migrations(): array
    {
        /** @var Migrator $migrator */
        $migrator = app('migrator');
        $ran = $migrator->getRepository()->getRan();
        $pending = array_filter(
            $migrator->getMigrationFiles($migrator->paths() ?: [database_path('migrations')]),
            fn ($file, $name) => ! in_array($name, $ran, true),
            ARRAY_FILTER_USE_BOTH
        );

        return [$pending === [], $pending === []
            ? count($ran).' migrations, none pending'
            : count($pending).' migration(s) not run: '.implode(', ', array_slice(array_keys($pending), 0, 3))];
    }

    private function cacheStore(): array
    {
        $store = config('cache.default');
        $key = 'preflight:'.bin2hex(random_bytes(8));

        Cache::put($key, 'ok', 10);
        $read = Cache::get($key);
        Cache::forget($key);

        return [$read === 'ok', $read === 'ok'
            ? "{$store} store round-tripped a value"
            : "{$store} store did not read back what it wrote"];
    }

    private function queue(): array
    {
        $connection = config('queue.default');

        if ($connection === 'sync') {
            return [! app()->isProduction(), 'QUEUE_CONNECTION=sync — mail and courier calls would run inside the request'];
        }

        // Reaching the backend is the point; a depth of zero is a healthy queue.
        $size = Queue::connection($connection)->size();

        return [true, "{$connection} reachable, {$size} job(s) waiting"];
    }

    private function scheduler(): array
    {
        $events = Schedule::events();

        return [$events !== [], $events !== []
            ? count($events).' scheduled task(s) registered'
            : 'no scheduled tasks — abandoned baskets would never be released'];
    }

    private function assets(): array
    {
        $manifest = public_path('build/manifest.json');

        if (! is_file($manifest)) {
            return [false, 'public/build/manifest.json is missing — run npm run build'];
        }

        $entries = array_keys((array) json_decode((string) file_get_contents($manifest), true));
        $wanted = ['resources/js/app.js', 'resources/js/storefront.js'];
        $missing = array_values(array_diff($wanted, $entries));

        return [$missing === [], $missing === []
            ? count($entries).' built entries, console and storefront both present'
            : 'manifest is missing '.implode(', ', $missing)];
    }

    private function ssr(): array
    {
        if (! config('inertia.ssr.enabled')) {
            return [null, 'INERTIA_SSR_ENABLED=false — pages render client-side only'];
        }

        $bundle = config('inertia.ssr.bundle');

        if (! is_file($bundle)) {
            return [false, 'SSR bundle missing — run npm run build:ssr'];
        }

        // The Inertia SSR server answers /health; a 200 means the node process
        // that goes with *this* release is up, not the one before it.
        $url = rtrim((string) config('inertia.ssr.url'), '/').'/health';

        try {
            $ok = Http::timeout(5)->get($url)->successful();
        } catch (Throwable $e) {
            return [false, "SSR server unreachable at {$url}: ".$e->getMessage()];
        }

        return [$ok, $ok ? "bundle built, server healthy at {$url}" : "SSR server at {$url} did not return 200"];
    }

    private function storageLink(): array
    {
        $link = public_path('storage');

        if (! file_exists($link)) {
            return [false, 'public/storage is missing — run php artisan storage:link, or product images 404'];
        }

        $target = is_link($link) ? readlink($link) : $link;

        return [is_dir($link), is_dir($link)
            ? "public/storage → {$target}"
            : "public/storage points at {$target}, which is not a directory"];
    }

    private function writablePaths(): array
    {
        $paths = [
            storage_path('app'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];

        $unwritable = array_values(array_filter($paths, fn ($p) => ! is_writable($p)));

        return [$unwritable === [], $unwritable === []
            ? count($paths).' paths writable'
            : 'not writable by the web user: '.implode(', ', $unwritable)];
    }

    private function frameworkCaches(): array
    {
        if (! app()->isProduction()) {
            return [null, 'only enforced in production'];
        }

        $missing = [];

        if (! is_file(base_path('bootstrap/cache/config.php'))) {
            $missing[] = 'config';
        }

        if (glob(base_path('bootstrap/cache/routes-v*.php')) === []) {
            $missing[] = 'routes';
        }

        return [$missing === [], $missing === []
            ? 'config and routes cached'
            : 'not cached: '.implode(', ', $missing).' — run php artisan optimize'];
    }

    private function sessionCookie(): array
    {
        if (parse_url((string) config('app.url'), PHP_URL_SCHEME) !== 'https') {
            return [null, 'APP_URL is not https — secure cookies would never be sent'];
        }

        $secure = config('session.secure');

        return [(bool) $secure, $secure
            ? 'SESSION_SECURE_COOKIE=true'
            : 'the site is https but the session cookie is not marked secure'];
    }

    private function mail(): array
    {
        $mailer = config('mail.default');
        $from = (string) config('mail.from.address');

        if (app()->isProduction() && in_array($mailer, ['log', 'array'], true)) {
            return [false, "MAIL_MAILER={$mailer} — order confirmations would go to a log file, not the customer"];
        }

        if ($from === '' || str_ends_with($from, '@example.com')) {
            return [false, "MAIL_FROM_ADDRESS is still '{$from}'"];
        }

        return [true, "{$mailer}, from {$from}"];
    }

    private function referenceData(): array
    {
        $counts = [
            'list_country' => ListCountry::query()->count(),
            'state_my' => StateMy::query()->count(),
            'postcode_my' => PostcodeMy::query()->count(),
        ];

        $empty = array_keys(array_filter($counts, fn ($n) => $n === 0));

        return [$empty === [], $empty === []
            ? implode(', ', array_map(fn ($t, $n) => "{$t} {$n}", array_keys($counts), $counts))
            : 'empty: '.implode(', ', $empty).' — run php artisan db:seed --class=ReferenceDataSeeder'];
    }
}
