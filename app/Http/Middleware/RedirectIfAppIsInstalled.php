<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAppIsInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $installed = Schema::hasTable('system_settings') && SystemSetting::get('installed_at') !== null;
        } catch (\Throwable) {
            // DB unreachable entirely (e.g. .env not configured yet) —
            // treat the same as "not installed", i.e. let them through.
            $installed = false;
        }

        if ($installed) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
