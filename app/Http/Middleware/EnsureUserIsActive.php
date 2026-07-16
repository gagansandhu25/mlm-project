<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status !== User::STATUS_ACTIVE) {
            abort(403, 'Your account is '.$user->status.'. Please contact support.');
        }

        return $next($request);
    }
}
