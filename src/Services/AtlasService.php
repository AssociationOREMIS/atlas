<?php

namespace Oremis\Atlas\Services;

use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class AtlasService
{
    public function __construct(
        private PioApiService $pio,
        private SyncUserService $sync
    ) {}

    /**
     * Redirect the user to Google SSO.
     */
    public function login()
    {
        $guard = config('atlas.guard');

        if (Auth::guard($guard)->check()) {
            return redirect()->intended(config('atlas.redirect_after_login'));
        }

        $driver = Socialite::driver('google')
            ->scopes(['email', 'profile']);

        // Apply additional Google parameters (hd, prompt...)
        if ($params = config('atlas.google_params')) {
            $driver = $driver->with($params);
        }

        /**
         * Support ?redirect_to=/some/page
         * We store it in a short-lived cookie.
         */
        $intended = request()->get('redirect_to');

        if ($intended && $this->isAllowedRedirectTarget($intended)) {
            cookie()->queue(cookie('atlas_intended', $intended, 15)); // 15 min
        }

        return $driver->redirect();
    }

    /**
     * Handle Google callback, fetch PIO user profile, sync local user, login.
     */
    public function callback()
    {
        $googleUser = Socialite::driver('google')->user();

        // The REAL token you need
        $accessToken = $googleUser->token;

        // Fetch the PIO profile using the access_token
        $profile = $this->pio->fetchProfile($accessToken);

        // Sync local user with PIO and Google profile
        $user = $this->sync->sync($profile, $googleUser);

        if ($user->status !== 'active') {
            // Optionally log
            \Log::warning("ATLAS: Inactive user attempted login", [
                'cib' => $user->cib,
                'email' => $user->email,
                'status' => $user->status,
                'ip' => request()->ip(),
            ]);

            return redirect('/')->with('error', 'Your account is inactive or suspended.');
        }

        Auth::guard(config('atlas.guard'))->login($user);

        /**
         * 1️If we have a custom redirect stored in cookie (redirect_to)
         */
        $cookieUrl = request()->cookie('atlas_intended');

        if ($cookieUrl) {
            // remove cookie after reading
            cookie()->queue(cookie()->forget('atlas_intended'));
            return redirect($cookieUrl);
        }

        return redirect()->intended(config('atlas.redirect_after_login'));
    }

    /**
     * Log out the user properly.
     */
    public function logout()
    {
        Auth::guard(config('atlas.guard', 'web'))->logout();

        session()->invalidate();
        session()->regenerateToken();

        return redirect(config('atlas.redirect_after_logout', '/'));
    }

    /**
     * Ensure redirect targets are safe (relative paths or approved hosts).
     */
    private function isAllowedRedirectTarget(string $url): bool
    {
        if (str_starts_with($url, '/') && !str_contains($url, '/auth/')) {
            return true;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        $host = $parts['host'] ?? null;
        $path = $parts['path'] ?? '';
        $scheme = $parts['scheme'] ?? null;

        if (!$host || !in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        if (str_contains($path, '/auth/')) {
            return false;
        }

        $allowedHosts = array_filter(array_map(
            'strtolower',
            (array) config('atlas.allowed_redirect_hosts', [])
        ));

        if (empty($allowedHosts)) {
            return false;
        }

        foreach ($allowedHosts as $allowedHost) {
            if ($this->hostMatches($host, $allowedHost)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a host matches a configured host or wildcard entry.
     */
    private function hostMatches(string $host, string $allowed): bool
    {
        $host = strtolower($host);
        $allowed = strtolower($allowed);

        if ($allowed === '*') {
            return true;
        }

        if (str_starts_with($allowed, '*.')) {
            $allowed = substr($allowed, 2);
            return $allowed ? $this->hostMatchesBase($host, $allowed) : false;
        }

        if (str_starts_with($allowed, '.')) {
            $allowed = ltrim($allowed, '.');
            return $allowed ? $this->hostMatchesBase($host, $allowed) : false;
        }

        return $host === $allowed;
    }

    private function hostMatchesBase(string $host, string $base): bool
    {
        if ($host === $base) {
            return true;
        }

        return str_ends_with($host, '.' . $base);
    }
}
