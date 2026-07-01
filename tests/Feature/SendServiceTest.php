<?php

use App\Models\Send;
use App\Models\User;
use App\Services\Interfaces\SendWriteServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(SendWriteServiceInterface::class);
});

it('creates a send from the given data and records its viewers', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $this->actingAs($author);

    $send = $this->service->createSend([
        'name' => 'My Secret',
        'message' => 'top secret',
        'expire_after' => '1 day',
        'viewers' => [$viewer->name],
    ]);

    expect($send)->toBeInstanceOf(Send::class)
        ->and($send->name)->toBe('My Secret')
        ->and($send->message)->toBe('top secret')
        ->and($send->user_id)->toBe($author->id);

    $this->assertDatabaseHas('sends', [
        'name' => 'My Secret',
        'user_id' => $author->id,
    ]);

    $this->assertDatabaseHas('send_user', [
        'user_id' => $viewer->id,
    ]);
});

it('deletes a send by its id', function () {
    $author = User::factory()->create();
    $this->actingAs($author);

    $send = $this->service->createSend([
        'name' => 'My Secret',
        'message' => 'top secret',
        'expire_after' => '1 day',
        'viewers' => [],
    ]);

    expect($this->service->deleteSend($send->id))->toBeTrue();

    $this->assertDatabaseMissing('sends', [
        'id' => $send->id,
    ]);
});
