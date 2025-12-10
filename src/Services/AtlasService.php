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

        if ($intended && str_starts_with($intended, '/') && !str_contains($intended, '/auth/')) {
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
}
