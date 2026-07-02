<?php

namespace App\Http\Middleware;

use App\Services\TurnstileVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTurnstile
{
    public function __construct(
        private readonly TurnstileVerifier $turnstileVerifier,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->turnstileVerifier->isEnabled()) {
            return $next($request);
        }

        if ($this->turnstileVerifier->verify($request->input('cf-turnstile-response'), $request)) {
            return $next($request);
        }

        return redirect()
            ->back()
            ->withInput($request->except('password', 'password_confirmation'))
            ->withErrors([
                'cf-turnstile-response' => __('Please complete the security check and try again.'),
            ]);
    }
}
