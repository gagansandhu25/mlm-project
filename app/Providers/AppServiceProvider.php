<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->ensureAppKeyExists();

        // A completely unreachable host (e.g. a fresh .env with default/
        // placeholder credentials) can otherwise hang past PHP's execution
        // time limit as an uncatchable fatal error, rather than a fast,
        // catchable exception below — this only bounds how long *connecting*
        // may take, not query execution once connected, so it's safe to
        // leave in place permanently, not just pre-install.
        $connection = config('database.default');

        if (in_array(config("database.connections.{$connection}.driver"), ['mysql', 'pgsql'], true)) {
            config(["database.connections.{$connection}.options" => [\PDO::ATTR_TIMEOUT => 3]]);
        }

        // Before the install wizard has run, `sessions`/`cache` may not
        // exist yet even though SESSION_DRIVER/CACHE_STORE are `database`.
        // Fall back to `file` so StartSession and SystemSetting's cache
        // calls don't crash pre-migration; this self-corrects the moment
        // the wizard runs `php artisan migrate`, with no code needed to
        // "switch back" — the next request just sees the tables exist.
        try {
            if (config('session.driver') === 'database' && ! Schema::hasTable('sessions')) {
                config(['session.driver' => 'file']);
            }

            if (config('cache.default') === 'database' && ! Schema::hasTable('cache')) {
                config(['cache.default' => 'file']);
            }
        } catch (\Throwable) {
            // DB_* isn't just missing tables — it may not even be
            // configured yet (a totally fresh .env). Force file for both
            // so the install wizard's own first page load still works.
            config(['session.driver' => 'file', 'cache.default' => 'file']);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Order::observe(OrderObserver::class);
    }

    /**
     * A blank APP_KEY makes EncryptCookies (part of every 'web' request,
     * before the install wizard's own code ever runs) throw immediately —
     * so a key needs to exist even earlier than the wizard's own APP_KEY
     * check. Generates one for this request and persists it to .env so
     * later requests don't each generate a different one (which would
     * invalidate cookies/CSRF between requests).
     */
    private function ensureAppKeyExists(): void
    {
        if (filled(config('app.key'))) {
            return;
        }

        $key = 'base64:'.base64_encode(random_bytes(32));
        config(['app.key' => $key]);

        $path = app()->environmentFilePath();
        $writable = file_exists($path) ? is_writable($path) : is_writable(dirname($path));

        if (! $writable) {
            return;
        }

        $content = file_exists($path) ? file_get_contents($path) : '';
        $pattern = '/^APP_KEY=.*/m';
        $line = 'APP_KEY='.$key;

        $content = preg_match($pattern, $content)
            ? preg_replace($pattern, $line, $content)
            : rtrim($content, "\n")."\n".$line."\n";

        file_put_contents($path, $content);
    }
}
