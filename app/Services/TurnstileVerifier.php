<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TurnstileVerifier
{
    public function isEnabled(): bool
    {
        return (bool) config('turnstile.enabled');
    }

    public function verify(?string $token, ?Request $request = null): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        if (! is_string($token) || $token === '') {
            return false;
        }

        $secretKey = config('turnstile.secret_key');

        if (! is_string($secretKey) || $secretKey === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post(config('turnstile.verify_url'), array_filter([
                    'secret' => $secretKey,
                    'response' => $token,
                    'remoteip' => $request?->ip(),
                ]))
                ->throw();
        } catch (ConnectionException|RequestException) {
            return false;
        }

        return (bool) $response->json('success');
    }
}
