<?php

use App\DTOs\SendData;
use App\Models\Send;
use App\Models\User;
use App\Repositories\Eloquent\SendRepository;
use App\Repositories\Interfaces\SendRepositoryInterface;
use App\Services\Interfaces\SendWriteServiceInterface;
use App\Support\SendIndexColumns;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = app(SendRepository::class);
});

it('returns all sends created by the given user', function () {
    $author = User::factory()->create();
    $otherUser = User::factory()->create();

    Send::forceCreate([
        'user_id' => $author->id,
        'message' => 'secret one',
        'name' => 'First Send',
        'valid_to' => now()->addDay(),
    ]);
    Send::forceCreate([
        'user_id' => $author->id,
        'message' => 'secret two',
        'name' => 'Second Send',
        'valid_to' => now()->addDay(),
    ]);
    Send::forceCreate([
        'user_id' => $otherUser->id,
        'message' => 'not mine',
        'name' => 'Other User Send',
        'valid_to' => now()->addDay(),
    ]);

    $sends = $this->repository->findAll((string) $author->id, SendIndexColumns::COLUMNS);

    expect($sends)->toHaveCount(2)
        ->and($sends->pluck('name')->all())->toBe(['First Send', 'Second Send'])
        ->and($sends->every(fn ($send) => $send->relationLoaded('authorizedUsers')))->toBeTrue();
});

it('returns an empty collection when the user has no sends', function () {
    $user = User::factory()->create();

    $sends = $this->repository->findAll((string) $user->id, SendIndexColumns::COLUMNS);

    expect($sends)->toBeEmpty();
});

it('throws when updating a send that does not exist', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $sendData = new SendData(
        userId: $user->id,
        message: 'updated secret',
        name: 'Updated Send',
        validTo: now()->addDay(),
    );

    $this->repository->update((string) Str::ulid(), $sendData);
})->throws(ModelNotFoundException::class);

it('caches findAll results by user id', function () {
    $author = User::factory()->create();
    $columns = SendIndexColumns::COLUMNS;

    Send::forceCreate([
        'user_id' => $author->id,
        'message' => 'secret one',
        'name' => 'First Send',
        'valid_to' => now()->addDay(),
    ]);

    $repository = app(SendRepositoryInterface::class);
    Cache::flush();

    $sends = $repository->findAll((string) $author->id, $columns);
    $cacheKey = 'sends_'.$author->id.'_'.hash('xxh128', json_encode(array_values($columns)));

    expect(Cache::has($cacheKey))->toBeTrue()
        ->and($sends)->toHaveCount(1)
        ->and($sends->first()->name)->toBe('First Send');
});

it('returns hydrated sends from cache without incomplete class errors', function () {
    $author = User::factory()->create();
    $columns = SendIndexColumns::COLUMNS;

    Send::forceCreate([
        'user_id' => $author->id,
        'message' => 'secret one',
        'name' => 'Cached Send',
        'valid_to' => now()->addDay(),
    ]);

    $repository = app(SendRepositoryInterface::class);
    Cache::flush();

    $repository->findAll((string) $author->id, $columns);

    $sends = $repository->findAll((string) $author->id, $columns);

    expect($sends)->toHaveCount(1)
        ->and($sends->first())->toBeInstanceOf(Send::class)
        ->and($sends->first()->name)->toBe('Cached Send');
});

it('invalidates findAll cache after creating a send', function () {
    $author = User::factory()->create();
    $columns = SendIndexColumns::COLUMNS;
    $cacheKey = 'sends_'.$author->id.'_'.hash('xxh128', json_encode(array_values($columns)));

    $repository = app(SendRepositoryInterface::class);
    Cache::flush();

    $this->actingAs($author);

    $repository->findAll((string) $author->id, $columns);

    expect(Cache::has($cacheKey))->toBeTrue();

    app(SendWriteServiceInterface::class)->createSend([
        'name' => 'New Send',
        'message' => 'secret',
        'expire_after' => '1 day',
    ]);

    expect(Cache::has($cacheKey))->toBeFalse();
});

it('deletes a send by its ulid', function () {
    $author = User::factory()->create();

    $send = Send::forceCreate([
        'user_id' => $author->id,
        'message' => 'secret',
        'name' => 'Delete Me',
        'valid_to' => now()->addDay(),
    ]);

    expect($this->repository->delete($send->id))->toBeTrue()
        ->and($this->repository->find($send->id))->toBeNull();
});
