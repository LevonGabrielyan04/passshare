<?php

use App\DTOs\SendData;
use App\Models\Send;
use App\Models\User;
use App\Repositories\Eloquent\CachedSendsRepository;
use App\Repositories\Interfaces\SendRepositoryInterface;
use App\Support\SendIndexColumns;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

afterEach(function () {
    Mockery::close();
});

/**
 * @return array<int, string>
 */
function indexColumns(): array
{
    return SendIndexColumns::COLUMNS;
}

function sendsListCacheKey(string $userId, array $columns): string
{
    return 'sends_'.$userId.'_'.hash('xxh128', json_encode(array_values($columns)));
}

function makeSend(?string $id = null, int $userId = 1, string $name = 'Test Send'): Send
{
    $id ??= (string) Str::ulid();

    return (new Send)->forceFill([
        'id' => $id,
        'user_id' => $userId,
        'message' => 'secret',
        'name' => $name,
        'valid_to' => now()->addDay(),
        'public_id' => (string) Str::uuid(),
    ]);
}

function serializeSend(Send $send): array
{
    $payload = [
        'attributes' => array_intersect_key(
            $send->getAttributes(),
            array_flip(indexColumns()),
        ),
        'relations' => [],
    ];

    foreach ($send->getRelations() as $name => $relation) {
        if ($relation instanceof Collection) {
            $payload['relations'][$name] = $relation
                ->map(fn ($model) => $model->getAttributes())
                ->all();
        }
    }

    return $payload;
}

function makeCachedRepository(
    ?SendRepositoryInterface $innerRepository = null,
    ?CacheRepository $cache = null,
): array {
    $innerRepository ??= Mockery::mock(SendRepositoryInterface::class);
    $cache ??= Mockery::mock(CacheRepository::class);

    return [
        new CachedSendsRepository($innerRepository, $cache),
        $innerRepository,
        $cache,
    ];
}

it('find caches only index columns for send attributes', function () {
    $send = makeSend();

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $cache->shouldReceive('get')
        ->once()
        ->with("send_{$send->id}")
        ->andReturnNull();

    $innerRepository->shouldReceive('find')
        ->once()
        ->with($send->id)
        ->andReturn($send);

    $cache->shouldReceive('put')
        ->once()
        ->with(
            "send_{$send->id}",
            Mockery::on(function (array $payload): bool {
                expect(array_keys($payload['attributes']))->toBe(indexColumns())
                    ->and($payload['attributes'])->not->toHaveKey('message');

                return true;
            }),
            Mockery::type(DateTimeInterface::class)
        );

    $repository->find($send->id);
});

it('find returns a hydrated send from cache without querying the inner repository', function () {
    $send = makeSend();
    $payload = serializeSend($send);

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $cache->shouldReceive('get')
        ->once()
        ->with("send_{$send->id}")
        ->andReturn($payload);

    $innerRepository->shouldNotReceive('find');

    $result = $repository->find($send->id);

    expect($result)->toBeInstanceOf(Send::class)
        ->and($result->id)->toBe($send->id)
        ->and($result->name)->toBe('Test Send');
});

it('find hydrates authorized users from cache', function () {
    $send = makeSend();
    $viewer = (new User)->forceFill([
        'id' => 5,
        'name' => 'Viewer',
        'email' => 'viewer@example.com',
    ]);
    $send->setRelation('authorizedUsers', new Collection([$viewer]));

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $cache->shouldReceive('get')
        ->once()
        ->with("send_{$send->id}")
        ->andReturn(serializeSend($send));

    $innerRepository->shouldNotReceive('find');

    $result = $repository->find($send->id);

    expect($result->relationLoaded('authorizedUsers'))->toBeTrue()
        ->and($result->authorizedUsers)->toHaveCount(1)
        ->and($result->authorizedUsers->first()->email)->toBe('viewer@example.com');
});

it('find caches the send when the inner repository returns a result', function () {
    $send = makeSend();

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $cache->shouldReceive('get')
        ->once()
        ->with("send_{$send->id}")
        ->andReturnNull();

    $innerRepository->shouldReceive('find')
        ->once()
        ->with($send->id)
        ->andReturn($send);

    $cache->shouldReceive('put')
        ->once()
        ->with(
            "send_{$send->id}",
            serializeSend($send),
            Mockery::type(DateTimeInterface::class)
        );

    $result = $repository->find($send->id);

    expect($result)->toBe($send);
});

it('find returns null without caching when the inner repository has no send', function () {
    $sendId = (string) Str::ulid();

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $cache->shouldReceive('get')
        ->once()
        ->with("send_{$sendId}")
        ->andReturnNull();

    $innerRepository->shouldReceive('find')
        ->once()
        ->with($sendId)
        ->andReturnNull();

    $cache->shouldNotReceive('put');

    expect($repository->find($sendId))->toBeNull();
});

it('find forgets invalid cache values before querying the inner repository', function () {
    $send = makeSend();

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $cache->shouldReceive('get')
        ->once()
        ->with("send_{$send->id}")
        ->andReturn('invalid-cache-value');

    $cache->shouldReceive('forget')
        ->once()
        ->with("send_{$send->id}");

    $innerRepository->shouldReceive('find')
        ->once()
        ->with($send->id)
        ->andReturn($send);

    $cache->shouldReceive('put')
        ->once()
        ->with(
            "send_{$send->id}",
            serializeSend($send),
            Mockery::type(DateTimeInterface::class)
        );

    expect($repository->find($send->id))->toBe($send);
});

it('findAll returns a hydrated collection from cache without querying the inner repository', function () {
    $send = makeSend(name: 'Cached Send');
    $userId = (string) $send->user_id;
    $columns = indexColumns();
    $cacheKey = sendsListCacheKey($userId, $columns);

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $cache->shouldReceive('get')
        ->once()
        ->with($cacheKey)
        ->andReturn([serializeSend($send)]);

    $innerRepository->shouldNotReceive('findAll');

    $result = $repository->findAll($userId, $columns);

    expect($result)->toHaveCount(1)
        ->and($result->first())->toBeInstanceOf(Send::class)
        ->and($result->first()->name)->toBe('Cached Send');
});

it('findAll caches the collection when the inner repository is queried', function () {
    $send = makeSend(name: 'Fresh Send');
    $userId = (string) $send->user_id;
    $columns = indexColumns();
    $cacheKey = sendsListCacheKey($userId, $columns);
    $collection = new Collection([$send]);

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $cache->shouldReceive('get')
        ->once()
        ->with($cacheKey)
        ->andReturnNull();

    $innerRepository->shouldReceive('findAll')
        ->once()
        ->with($userId, $columns)
        ->andReturn($collection);

    $cache->shouldReceive('put')
        ->once()
        ->with(
            $cacheKey,
            [serializeSend($send)],
            Mockery::type(DateTimeInterface::class)
        );

    $result = $repository->findAll($userId, $columns);

    expect($result)->toBe($collection);
});

it('findAll forgets invalid cache values before querying the inner repository', function () {
    $send = makeSend();
    $userId = (string) $send->user_id;
    $columns = indexColumns();
    $cacheKey = sendsListCacheKey($userId, $columns);
    $collection = new Collection([$send]);

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $cache->shouldReceive('get')
        ->once()
        ->with($cacheKey)
        ->andReturn('invalid-cache-value');

    $cache->shouldReceive('forget')
        ->once()
        ->with($cacheKey);

    $innerRepository->shouldReceive('findAll')
        ->once()
        ->with($userId, $columns)
        ->andReturn($collection);

    $cache->shouldReceive('put')
        ->once()
        ->with(
            $cacheKey,
            [serializeSend($send)],
            Mockery::type(DateTimeInterface::class)
        );

    expect($repository->findAll($userId, $columns))->toBe($collection);
});

it('create stores the send in cache and invalidates the user send list cache', function () {
    $send = makeSend();
    $userId = (string) $send->user_id;
    $columns = indexColumns();
    $sendData = new SendData(
        userId: $send->user_id,
        message: 'secret',
        name: 'Test Send',
        validTo: now()->addDay(),
        id: $send->id,
    );

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $innerRepository->shouldReceive('create')
        ->once()
        ->with($sendData, [])
        ->andReturn($send);

    $cache->shouldReceive('put')
        ->once()
        ->with(
            "send_{$send->id}",
            serializeSend($send),
            Mockery::type(DateTimeInterface::class)
        );

    $cache->shouldReceive('forget')
        ->once()
        ->with("sends_{$userId}");

    $cache->shouldReceive('forget')
        ->once()
        ->with(sendsListCacheKey($userId, $columns));

    $result = $repository->create($sendData);

    expect($result)->toBe($send);
});

it('update refreshes the send cache and invalidates the user send list cache', function () {
    $send = makeSend(name: 'Updated Send');
    $userId = (string) $send->user_id;
    $columns = indexColumns();
    $sendData = new SendData(
        userId: $send->user_id,
        message: 'updated secret',
        name: 'Updated Send',
        validTo: now()->addDay(),
    );

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $innerRepository->shouldReceive('update')
        ->once()
        ->with($send->id, $sendData, [])
        ->andReturn($send);

    $cache->shouldReceive('put')
        ->once()
        ->with(
            "send_{$send->id}",
            serializeSend($send),
            Mockery::type(DateTimeInterface::class)
        );

    $cache->shouldReceive('forget')
        ->once()
        ->with("sends_{$userId}");

    $cache->shouldReceive('forget')
        ->once()
        ->with(sendsListCacheKey($userId, $columns));

    $result = $repository->update($send->id, $sendData);

    expect($result)->toBe($send);
});

it('delete invalidates send and user list caches before deleting from the inner repository', function () {
    $send = makeSend();
    $userId = (string) $send->user_id;
    $columns = indexColumns();
    $numericId = 42;

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $innerRepository->shouldReceive('find')
        ->once()
        ->with((string) $numericId)
        ->andReturn($send);

    $cache->shouldReceive('forget')
        ->once()
        ->with("send_{$numericId}");

    $cache->shouldReceive('forget')
        ->once()
        ->with("sends_{$userId}");

    $cache->shouldReceive('forget')
        ->once()
        ->with(sendsListCacheKey($userId, $columns));

    $innerRepository->shouldReceive('delete')
        ->once()
        ->with($numericId)
        ->andReturnTrue();

    expect($repository->delete($numericId))->toBeTrue();
});

it('delete forgets only the send cache when the send cannot be found', function () {
    $numericId = 99;

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $innerRepository->shouldReceive('find')
        ->once()
        ->with((string) $numericId)
        ->andReturnNull();

    $cache->shouldReceive('forget')
        ->once()
        ->with("send_{$numericId}");

    $innerRepository->shouldReceive('delete')
        ->once()
        ->with($numericId)
        ->andReturnTrue();

    expect($repository->delete($numericId))->toBeTrue();
});

it('find uses valid_to as cache expiration when it is sooner than the configured ttl', function () {
    Carbon::setTestNow(now());
    config(['send.cache_ttl' => 60]);

    $send = makeSend();
    $send->valid_to = now()->addMinutes(30);

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $cache->shouldReceive('get')
        ->once()
        ->with("send_{$send->id}")
        ->andReturnNull();

    $innerRepository->shouldReceive('find')
        ->once()
        ->with($send->id)
        ->andReturn($send);

    $cache->shouldReceive('put')
        ->once()
        ->with(
            "send_{$send->id}",
            serializeSend($send),
            Mockery::on(fn (DateTimeInterface $expiresAt): bool => Carbon::parse($expiresAt)->equalTo(now()->addMinutes(30)))
        );

    $repository->find($send->id);
});

it('findAll uses the earliest valid_to as cache expiration when it is sooner than the configured ttl', function () {
    Carbon::setTestNow(now());
    config(['send.cache_ttl' => 60]);

    $send = makeSend(name: 'Sooner Send');
    $send->valid_to = now()->addMinutes(45);
    $otherSend = makeSend(name: 'Later Send', userId: $send->user_id);
    $otherSend->valid_to = now()->addMinutes(90);
    $userId = (string) $send->user_id;
    $columns = indexColumns();
    $cacheKey = sendsListCacheKey($userId, $columns);
    $collection = new Collection([$send, $otherSend]);

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $cache->shouldReceive('get')
        ->once()
        ->with($cacheKey)
        ->andReturnNull();

    $innerRepository->shouldReceive('findAll')
        ->once()
        ->with($userId, $columns)
        ->andReturn($collection);

    $cache->shouldReceive('put')
        ->once()
        ->with(
            $cacheKey,
            [serializeSend($send), serializeSend($otherSend)],
            Mockery::on(fn (DateTimeInterface $expiresAt): bool => Carbon::parse($expiresAt)->equalTo(now()->addMinutes(45)))
        );

    $repository->findAll($userId, $columns);
});
