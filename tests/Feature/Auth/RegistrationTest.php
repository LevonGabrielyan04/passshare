<?php

use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());

    fakeUncompromisedPasswordChecks();
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response
        ->assertOk()
        ->assertSee(__('Nickname'), false)
        ->assertSee(__('Email address (optional)'), false)
        ->assertSee(__('Optional. Add an email if you want password recovery and email-based features.'), false);
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'ValidPassword-15',
        'password_confirmation' => 'ValidPassword-15',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('new users can register without an email address', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'No Email User',
        'password' => 'ValidPassword-15',
        'password_confirmation' => 'ValidPassword-15',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    $user = User::query()->where('name', 'No Email User')->first();

    expect($user)->not->toBeNull()
        ->and($user->email)->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeTrue();
});

test('registration is throttled to five requests per minute', function () {
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->post(route('register.store'), [
            'name' => "User {$attempt}",
            'password' => 'ValidPassword-15',
            'password_confirmation' => 'ValidPassword-15',
        ])->assertStatus(302);
    }

    $response = $this->post(route('register.store'), [
        'name' => 'Throttled User',
        'password' => 'ValidPassword-15',
        'password_confirmation' => 'ValidPassword-15',
    ]);

    $response->assertStatus(429);
});

test('registration rejects duplicate nicknames', function () {
    User::factory()->create(['name' => 'Taken Nickname']);

    $response = $this->post(route('register.store'), [
        'name' => 'Taken Nickname',
        'email' => 'another@example.com',
        'password' => 'ValidPassword-15',
        'password_confirmation' => 'ValidPassword-15',
    ]);

    $response->assertSessionHasErrors('name');
    $this->assertGuest();
});
