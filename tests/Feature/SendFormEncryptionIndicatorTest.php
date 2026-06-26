<?php

use App\Models\User;
use App\Services\Interfaces\SendServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;

uses(RefreshDatabase::class);

it('shows the encryption indicator on the create form', function () {
    $user = User::factory()->create();
    $minLength = config('send.password.min_length');
    $passwordRules = \Illuminate\Validation\Rules\Password::min(16)->mixedCase()->numbers()->symbols()->toPasswordRulesString();

    $this->actingAs($user)
        ->get(route('sends.create'))
        ->assertOk()
        ->assertSee('Encryption in progress', false)
        ->assertSee('loading-spinner', false)
        ->assertSee(':disabled="isEncrypting"', false)
        ->assertSee('minlength="'.$minLength.'"', false)
        ->assertSee('passwordrules="'.$passwordRules.'"', false)
        ->assertSee('x-data="viewerManager"', false)
        ->assertSee('data-min-password-length="'.$minLength.'"', false);
});

it('shows the encryption indicator on the edit form view', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $this->actingAs($author);

    $send = app(SendServiceInterface::class)->createSend([
        'name' => 'Editable',
        'message' => 'secret',
        'expire_after' => '1 day',
        'viewers' => [$viewer->email],
    ]);

    $html = view('sends.edit', [
        'send' => $send,
        'errors' => new ViewErrorBag,
    ])->render();

    $minLength = config('send.password.min_length');

    expect($html)
        ->toContain('Encryption in progress')
        ->toContain('loading-spinner')
        ->toContain(':disabled="isEncrypting"')
        ->toContain('minlength="'.$minLength.'"')
        ->toContain('data-min-password-length="'.$minLength.'"')
        ->toMatch('/x-data="viewerManager"/');
});
