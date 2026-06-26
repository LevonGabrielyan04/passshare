<?php

use App\Models\User;

it('rejects sends without any viewers', function () {
    $author = User::factory()->create();

    $this->actingAs($author)
        ->post(route('sends.store'), [
            'name' => 'My Secret',
            'message' => 'top secret',
            'expire_after' => '1 day',
            'viewers' => [],
        ])
        ->assertSessionHasErrors('viewers');
});

it('rejects viewer emails that are not registered users', function () {
    $author = User::factory()->create();

    $this->actingAs($author)
        ->post(route('sends.store'), [
            'name' => 'My Secret',
            'message' => 'top secret',
            'expire_after' => '1 day',
            'viewers' => ['unknown@example.com'],
        ])
        ->assertSessionHasErrors([
            'viewers.0' => 'Email address number 1 is not found in our registered users table.',
        ]);
});

it('rejects messages that exceed the configured max length', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $maxLength = config('send.message.encrypted_max_length');

    $this->actingAs($author)
        ->post(route('sends.store'), [
            'name' => 'My Secret',
            'message' => str_repeat('a', $maxLength + 1),
            'expire_after' => '1 day',
            'viewers' => [$viewer->email],
        ])
        ->assertSessionHasErrors('message');
});

it('accepts viewer emails that belong to registered users', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();

    $this->actingAs($author)
        ->post(route('sends.store'), [
            'name' => 'My Secret',
            'message' => 'top secret',
            'expire_after' => '1 day',
            'viewers' => [$viewer->email],
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasNoErrors();
});
