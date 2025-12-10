# OREMIS Atlas

Atlas is a Laravel package that provides Single Sign-On (SSO) for OREMIS projects by combining Google OAuth (via Socialite) with the PIO Identity service. It handles fetching the PIO profile, synchronizing a local user record, and enforcing account status via middleware.

**Supported:** PHP >= 8.1, Laravel 10.x, 11.x, 12.x

## Installation

Install via Composer:

```bash
composer require oremis/atlas
```

The package supports Laravel auto-discovery. It also registers a `Atlas` facade (see `composer.json` `extra` section).

### Publish configuration

Publish the package config to your application:

```bash
php artisan vendor:publish --tag=atlas-config
```

This creates `config/atlas.php`. The package merges some Google Socialite `services.google` values automatically if they are not already present (see `AtlasServiceProvider`).

## Configuration

Key options in `config/atlas.php`:

- `pio_url` : Base URL of PIO (default `https://pio.oremis.fr`).
- `pio_me` : PIO `/me` endpoint (default `/api/me`).
- `guard` : Auth guard to use (default `web`).
- `google_params` : Extra Google OAuth params applied on login (e.g. `hd`, `prompt`).
- `redirect_after_login` / `redirect_after_logout` : Redirect targets.
- `user_model` : The Eloquent user model class.
- `status_field` : Column name used to check user status (default `status`).
- `suspended_values` : Array of statuses considered blocked (default `['suspended', 'inactive']`).
- `status_cache_ttl` : Seconds to cache the user status in `CheckUserStatus` middleware (default `120`).

## Environment / Services setup

Add the Google credentials and (optionally) override PIO settings in your `.env`:

```env
# PIO Identity Service
ATLAS_PIO_URL=https://pio.oremis.fr
ATLAS_PIO_ME_ENDPOINT=/api/me

# Google Socialite (if you don't set these, the package will try to merge them):
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URL=https://your-app.com/auth/callback
```

You can also add values to `config/services.php` under `google` (the package will not override existing keys):

```php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_REDIRECT_URL'),
],
```

## Database / Model requirements

The package expects your user model (configurable via `user_model`) to support a few columns. At minimum you should add these columns to your `users` table (or the model you use):

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('cib')->nullable()->index();       // PIO unique id
    $table->string('google_id')->nullable()->index(); // Google account id
    $table->string('status')->default('active');     // status checked by middleware
    $table->json('profile_data')->nullable();         // any extra PIO profile data
    $table->timestamp('last_login_at')->nullable();   // optional
});
```

Update your model to allow mass-assignment and cast `profile_data`:

```php
// App\Models\User
protected $fillable = [
    'email', 'password', 'cib', 'google_id', 'first_name', 'last_name', 'status', 'profile_data',
];

protected $casts = [
    'profile_data' => 'array',
];
```

Tip: The `SyncUserService` will create a random password if the `password` column exists.

## Routes / Usage

You can use the `Atlas` facade or inject the `Oremis\Atlas\Services\AtlasService`.

Example routes in `routes/web.php`:

```php
use Oremis\Atlas\Facades\Atlas;
use Illuminate\Support\Facades\Route;

Route::get('/login', fn() => Atlas::login())->name('login');
Route::get('/auth/callback', fn() => Atlas::callback());
Route::post('/logout', fn() => Atlas::logout())->name('logout');
```

Notes on behavior:

- `Atlas::login()` redirects to Google's OAuth page and accepts an optional `?redirect_to=/path` parameter that is stored in a short-lived cookie (15 min) so the user returns to the requested page after login.
- `Atlas::callback()` exchanges the Google token with PIO using `/api/me`, synchronizes (or creates) the local user via `SyncUserService`, and logs them in. If the user is not `active`, the callback will redirect with an error.
- `Atlas::logout()` logs out via the configured guard and redirects to `redirect_after_logout`.

## Middleware

Register `\Oremis\Atlas\Middleware\CheckUserStatus` in your `app/Http/Kernel.php` as a route or global middleware depending on your needs. The middleware caches the user's status (configurable TTL) and logs them out if their status is in `suspended_values`.

### Laravel 11 & 12

In `bootstrap/app.php`:

```php
// bootstrap/app.php
use Oremis\Atlas\Middleware\CheckUserStatus;

->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        CheckUserStatus::class,
    ]);
})
```

### Laravel 10

In `app/Http/Kernel.php`:

```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        // ...
        \Oremis\Atlas\Middleware\CheckUserStatus::class,
    ],
];
```

Or register it as a route middleware and add it to routes you want protected.

## Error handling and logging

The package throws a `RuntimeException` if the call to PIO fails. In production you may want to catch/handle errors around `Atlas::callback()` to present a friendly message and log details.

## Testing and development

- Add tests that mock `Socialite` and `Http::fake()` for the PIO `/me` endpoint to validate the sync logic.
- Make sure your `user_model` has the expected fields before enabling the package in production.

## Contributing

Contributions and bug reports are welcome. Please open PRs against the repository and include tests for new behavior.

## License

This package is licensed under AGPL-3.0-or-later (see `LICENSE`).
