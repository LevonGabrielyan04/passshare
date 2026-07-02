<?php

use App\Models\User;
use App\Services\TurnstileVerifier;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

test('registration screen includes turnstile when enabled', function () {
    $this->skipUnlessFortifyHas(Features::registration());

    enableTurnstileForTests();

    $response = $this->get(route('register'));

    $response
        ->assertOk()
        ->assertSee('cf-turnstile', false)
        ->assertSee('1x00000000000000000AA', false)
        ->assertSee('challenges.cloudflare.com/turnstile/v0/api.js', false)
        ->assertSee('nonce="', false);
});

test('registration rejects submissions without a turnstile token when enabled', function () {
    $this->skipUnlessFortifyHas(Features::registration());

    fakeUncompromisedPasswordChecks();
    enableTurnstileForTests();
    fakeTurnstileVerification();

    $response = $this->post(route('register.store'), [
        'name' => 'Turnstile User',
        'password' => 'ValidPassword-15',
        'password_confirmation' => 'ValidPassword-15',
    ]);

    $response->assertSessionHasErrors('cf-turnstile-response');
    $this->assertGuest();
});

test('registration rejects submissions when turnstile verification fails', function () {
    $this->skipUnlessFortifyHas(Features::registration());

    fakeUncompromisedPasswordChecks();
    enableTurnstileForTests();
    fakeTurnstileVerification(success: false);

    $response = $this->post(route('register.store'), [
        'name' => 'Turnstile User',
        'password' => 'ValidPassword-15',
        'password_confirmation' => 'ValidPassword-15',
        'cf-turnstile-response' => 'invalid-token',
    ]);

    $response->assertSessionHasErrors('cf-turnstile-response');
    $this->assertGuest();
});

test('registration succeeds when turnstile verification passes', function () {
    $this->skipUnlessFortifyHas(Features::registration());

    fakeUncompromisedPasswordChecks();
    enableTurnstileForTests();
    fakeTurnstileVerification();

    $response = $this->post(route('register.store'), [
        'name' => 'Turnstile User',
        'password' => 'ValidPassword-15',
        'password_confirmation' => 'ValidPassword-15',
        'cf-turnstile-response' => 'valid-token',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('login screen includes turnstile when enabled', function () {
    enableTurnstileForTests();

    $response = $this->get(route('login'));

    $response
        ->assertOk()
        ->assertSee('cf-turnstile', false)
        ->assertSee('1x00000000000000000AA', false)
        ->assertSee('challenges.cloudflare.com/turnstile/v0/api.js', false)
        ->assertSee('nonce="', false);
});

test('login rejects submissions without a turnstile token when enabled', function () {
    $user = User::factory()->create();
    enableTurnstileForTests();
    fakeTurnstileVerification();

    $response = $this->post(route('login.store'), [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('cf-turnstile-response');
    $this->assertGuest();
});

test('login rejects submissions when turnstile verification fails', function () {
    $user = User::factory()->create();
    enableTurnstileForTests();
    fakeTurnstileVerification(success: false);

    $response = $this->post(route('login.store'), [
        'login' => $user->email,
        'password' => 'password',
        'cf-turnstile-response' => 'invalid-token',
    ]);

    $response->assertSessionHasErrors('cf-turnstile-response');
    $this->assertGuest();
});

test('login succeeds when turnstile verification passes', function () {
    $user = User::factory()->create();
    enableTurnstileForTests();
    fakeTurnstileVerification();

    $response = $this->post(route('login.store'), [
        'login' => $user->email,
        'password' => 'password',
        'cf-turnstile-response' => 'valid-token',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('forgot password screen includes turnstile when enabled', function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());

    enableTurnstileForTests();

    $response = $this->get(route('password.request'));

    $response
        ->assertOk()
        ->assertSee('cf-turnstile', false)
        ->assertSee('1x00000000000000000AA', false)
        ->assertSee('challenges.cloudflare.com/turnstile/v0/api.js', false)
        ->assertSee('nonce="', false);
});

test('forgot password rejects submissions without a turnstile token when enabled', function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());

    $user = User::factory()->create();
    enableTurnstileForTests();
    fakeTurnstileVerification();

    $response = $this->post(route('password.email'), [
        'email' => $user->email,
    ]);

    $response->assertSessionHasErrors('cf-turnstile-response');
});

test('forgot password rejects submissions when turnstile verification fails', function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());

    $user = User::factory()->create();
    enableTurnstileForTests();
    fakeTurnstileVerification(success: false);

    $response = $this->post(route('password.email'), withTurnstileToken([
        'email' => $user->email,
    ], token: 'invalid-token'));

    $response->assertSessionHasErrors('cf-turnstile-response');
});

test('forgot password succeeds when turnstile verification passes', function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());

    Notification::fake();

    $user = User::factory()->create();
    enableTurnstileForTests();
    fakeTurnstileVerification();

    $response = $this->post(route('password.email'), withTurnstileToken([
        'email' => $user->email,
    ]));

    $response->assertSessionHasNoErrors();

    Notification::assertSentTo($user, ResetPassword::class);
});

test('turnstile verifier skips validation when disabled', function () {
    config([
        'turnstile.site_key' => null,
        'turnstile.secret_key' => null,
        'turnstile.enabled' => false,
    ]);

    $verifier = app(TurnstileVerifier::class);

    expect($verifier->isEnabled())->toBeFalse()
        ->and($verifier->verify(null))->toBeTrue();
});
