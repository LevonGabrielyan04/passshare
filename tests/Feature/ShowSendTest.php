<?php

use App\Models\User;
use App\Services\Interfaces\SendWriteServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(SendWriteServiceInterface::class);
});

it('shows a send resolved by its public id to an authorized viewer', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $this->actingAs($author);

    $send = $this->service->createSend([
        'name' => 'My Secret',
        'message' => 'top secret',
        'expire_after' => '1 day',
        'viewers' => [$viewer->name],
    ]);

    $minLength = config('send.password.min_length');

    $this->actingAs($viewer)
        ->get(route('sends.show', $send))
        ->assertOk()
        ->assertViewIs('sends.show')
        ->assertViewHas('send', fn ($value) => $value->is($send))
        ->assertSee('My Secret')
        ->assertSee($send->public_id)
        ->assertSee('top secret')
        ->assertSee($viewer->name)
        ->assertSee('x-data="sendDetailsManager"', false)
        ->assertSee('data-raw-message=', false)
        ->assertSee('data-min-password-length="'.$minLength.'"', false)
        ->assertSee('Decryption in progress', false)
        ->assertSee('This message is password protected', false)
        ->assertDontSee("sendDetailsManager('top secret', {$minLength})", false);
});

it('shows the decryption UI for password-protected sends', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $this->actingAs($author);

    $encryptedMessage = json_encode([
        'ciphertext' => 'encrypted-payload',
        'salt' => 'salt-value',
        'iv' => 'iv-value',
    ]);

    $send = $this->service->createSend([
        'name' => 'Locked Send',
        'message' => $encryptedMessage,
        'expire_after' => '1 day',
        'viewers' => [$viewer->name],
    ]);

    $minLength = config('send.password.min_length');

    $this->actingAs($viewer)
        ->get(route('sends.show', $send))
        ->assertOk()
        ->assertSee('Locked Send')
        ->assertSee($viewer->name)
        ->assertSee('This message is password protected', false)
        ->assertSee('Decryption in progress', false)
        ->assertSee('x-data="sendDetailsManager"', false)
        ->assertSee('data-raw-message=', false)
        ->assertSee('data-min-password-length="'.$minLength.'"', false)
        ->assertSee(':disabled="isDecrypting"', false)
        ->assertSee('Decrypt', false)
        ->assertDontSee('sendDetailsManager(', false);
});

it('forbids viewing a send for an unauthorized user', function () {
    $denialLog = null;

    Log::listen(function (MessageLogged $event) use (&$denialLog) {
        if ($event->message === 'Policy authorization denied') {
            $denialLog = $event;
        }
    });

    $author = User::factory()->create();
    $stranger = User::factory()->create();
    $this->actingAs($author);

    $send = $this->service->createSend([
        'name' => 'My Secret',
        'message' => 'top secret',
        'expire_after' => '1 day',
        'viewers' => [],
    ]);

    $this->actingAs($stranger)
        ->get(route('sends.show', $send))
        ->assertNotFound();

    expect($denialLog)->not->toBeNull()
        ->and($denialLog->level)->toBe('warning')
        ->and($denialLog->context['method'])->toBe('GET')
        ->and($denialLog->context['user_id'])->toBe($stranger->id)
        ->and($denialLog->context['url'])->toBe(route('sends.show', $send))
        ->and($denialLog->context['ip'])->not->toBeEmpty();
});
