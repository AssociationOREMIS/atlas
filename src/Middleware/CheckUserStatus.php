<?php

namespace Oremis\Atlas\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class CheckUserStatus
{
    public function handle($request, Closure $next)
    {
        $guard = config('atlas.guard');
        $user = Auth::guard($guard)->user();

        if (!$user) return $next($request);

        $field = config('atlas.status_field');
        $blocked = config('atlas.suspended_values');
        $ttl = config('atlas.status_cache_ttl');

        $key = "atlas_status_{$guard}_{$user->id}";

        $status = Cache::remember($key, $ttl, fn() => $user->{$field});

        if (in_array($status, $blocked)) {
            Auth::guard($guard)->logout();
            Cache::forget($key);

            return redirect(config('atlas.redirect_after_logout'))
                ->with('atlas_blocked', true);
        }

        return $next($request);
    }
}
