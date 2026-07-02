<?php

namespace App\Providers;

use App\Actions\Fortify\AuthenticateUser;
use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureRegistrationRateLimiting();
        $this->configureTurnstileProtectedRoutes();
        $this->configureEmailVerificationRoute();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::authenticateUsing(new AuthenticateUser);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('pages::auth.login'));
        Fortify::verifyEmailView(fn () => view('pages::auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('pages::auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('pages::auth.confirm-password'));
        Fortify::registerView(fn () => view('pages::auth.register'));
        Fortify::resetPasswordView(fn () => view('pages::auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('pages::auth.forgot-password'));
    }

    /**
     * Use a guest-accessible controller for signed email verification links.
     */
    private function configureEmailVerificationRoute(): void
    {
        if (! Features::enabled(Features::emailVerification())) {
            return;
        }

        $this->app->booted(function (): void {
            $this->app->booted(function (): void {
                $route = Route::getRoutes()->getByName('verification.verify');

                if ($route === null) {
                    return;
                }

                $authMiddleware = config('fortify.auth_middleware', 'auth');

                $action = $route->getAction();
                $middleware = $action['middleware'] ?? [];

                if (! is_array($middleware)) {
                    $middleware = [$middleware];
                }

                $action['middleware'] = collect($middleware)
                    ->reject(fn (mixed $middleware): bool => is_string($middleware) && str_starts_with($middleware, $authMiddleware))
                    ->values()
                    ->all();
                $action['uses'] = VerifyEmailController::class.'@__invoke';
                $action['controller'] = VerifyEmailController::class.'@__invoke';

                $route->setAction($action);
            });
        });
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())));

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()),
            );
        });

        RateLimiter::for('registration', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }

    /**
     * Require Turnstile verification for protected Fortify submissions.
     */
    private function configureTurnstileProtectedRoutes(): void
    {
        $this->app->booted(function (): void {
            $this->app->booted(function (): void {
                foreach (['register.store', 'login.store', 'password.email'] as $routeName) {
                    $route = Route::getRoutes()->getByName($routeName);

                    if ($route === null) {
                        continue;
                    }

                    $route->middleware('turnstile');
                }
            });
        });
    }

    /**
     * Apply registration rate limiting to Fortify registration routes.
     */
    private function configureRegistrationRateLimiting(): void
    {
        $limiter = config('fortify.limiters.registration');

        if ($limiter === null) {
            return;
        }

        $this->app->booted(function () use ($limiter): void {
            $this->app->booted(function () use ($limiter): void {
                foreach (['register', 'register.store'] as $routeName) {
                    $route = Route::getRoutes()->getByName($routeName);

                    if ($route === null) {
                        continue;
                    }

                    $route->middleware('throttle:'.$limiter);
                }
            });
        });
    }
}
