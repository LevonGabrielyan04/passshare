<?php

use App\Models\Send;
use App\Models\User;
use App\Services\Interfaces\SendWriteServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows an empty state when the user has no sends', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertViewIs('dashboard')
        ->assertViewHas('sends', fn ($sends) => $sends->isEmpty())
        ->assertSee('No sends yet.')
        ->assertSee('Create your first send');
});

it('lists sends belonging to the authenticated user', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($author);

    $send = app(SendWriteServiceInterface::class)->createSend([
        'name' => 'My Secret',
        'message' => fakeEncryptedMessage(),
        'expire_after' => '1 day',
        'viewers' => [$viewer->name],
    ]);

    Send::forceCreate([
        'user_id' => $otherUser->id,
        'message' => 'not mine',
        'name' => 'Other User Send',
        'valid_to' => now()->addDay(),
    ]);

    $this->actingAs($author)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertViewIs('dashboard')
        ->assertSee('My Secret')
        ->assertDontSee('Other User Send')
        ->assertSee(route('sends.show', $send), false)
        ->assertViewHas('sends', fn ($sends) => $sends->count() === 1 && $sends->first()->is($send));
});

it('shows a success message after creating a send', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();

    $this->actingAs($author)
        ->post(route('sends.store'), [
            'name' => 'My Secret',
            'message' => fakeEncryptedMessage(),
            'expire_after' => '1 day',
            'viewers' => [$viewer->name],
        ])
        ->assertRedirect(route('dashboard'));

    $this->actingAs($author)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Send created successfully.')
        ->assertSee('My Secret');
});

it('redirects unauthenticated users to login', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

it('shows the app name and no starter kit links in the layout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(config('app.name'), false)
        ->assertDontSee('Laravel Starter Kit', false)
        ->assertDontSee('https://github.com/laravel/livewire-starter-kit', false)
        ->assertDontSee('https://laravel.com/docs/starter-kits#livewire', false);
});
