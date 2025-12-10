<?php

namespace Oremis\Atlas\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class PioApiService
{
    public function fetchProfile(string $googleToken): array
    {
        $url = rtrim(config('atlas.pio_url'), '/')
            . '/'
            . ltrim(config('atlas.pio_me'), '/');

        $response = Http::withToken($googleToken)->get($url);

        if (!$response->successful()) {
            throw new RuntimeException('PIO /me request failed: ' . $response->status());
        }

        return $response->json('data');
    }
}
