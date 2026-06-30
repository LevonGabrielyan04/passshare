<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyEmailRequest;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\VerifyEmailResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the user's email address as verified.
     */
    public function __invoke(VerifyEmailRequest $request): VerifyEmailResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            Auth::login($user);

            return app(VerifyEmailResponse::class);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        Auth::login($user);

        return app(VerifyEmailResponse::class);
    }
}
