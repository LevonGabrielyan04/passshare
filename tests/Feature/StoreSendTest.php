<?php

use App\Models\User;

it('rejects sends without any viewers', function () {
    $author = User::factory()->create();

    $this->actingAs($author)
        ->post(route('sends.store'), [
            'name' => 'My Secret',
            'message' => fakeEncryptedMessage(),
            'expire_after' => '1 day',
            'viewers' => [],
        ])
        ->assertSessionHasErrors('viewers');
});

it('rejects viewer names that are not registered users', function () {
    $author = User::factory()->create();

    $this->actingAs($author)
        ->post(route('sends.store'), [
            'name' => 'My Secret',
            'message' => fakeEncryptedMessage(),
            'expire_after' => '1 day',
            'viewers' => ['Unknown User'],
        ])
        ->assertSessionHasErrors([
            'viewers.0' => 'User name "Unknown User" is not found in our registered users table.',
        ]);
});

it('rejects plaintext messages', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();

    $this->actingAs($author)
        ->post(route('sends.store'), [
            'name' => 'My Secret',
            'message' => 'top secret',
            'expire_after' => '1 day',
            'viewers' => [$viewer->name],
        ])
        ->assertSessionHasErrors('message');
});

it('rejects messages that exceed the configured max length', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $maxLength = config('send.message.encrypted_max_length');
    $message = fakeEncryptedMessage(4_000);

    expect(strlen($message))->toBeGreaterThan($maxLength);

    $this->actingAs($author)
        ->post(route('sends.store'), [
            'name' => 'My Secret',
            'message' => $message,
            'expire_after' => '1 day',
            'viewers' => [$viewer->name],
        ])
        ->assertSessionHasErrors('message');
});

it('accepts viewer names that belong to registered users', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();

    $this->actingAs($author)
        ->post(route('sends.store'), [
            'name' => 'My Secret',
            'message' => fakeEncryptedMessage(),
            'expire_after' => '1 day',
            'viewers' => [$viewer->name],
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasNoErrors();
});
