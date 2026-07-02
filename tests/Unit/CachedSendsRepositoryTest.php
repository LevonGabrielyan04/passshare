<?php

use App\DTOs\SendData;
use App\Repositories\Eloquent\CachedSendsRepository;
use App\Repositories\Interfaces\SendRepositoryInterface;
use App\Support\SendIndexColumns;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Factories\SendFactory;
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

it('find returns a cached send without querying the inner repository', function () {
    $send = SendFactory::make();

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $cache->shouldReceive('remember')
        ->once()
        ->with("send_{$send->id}", Mockery::type(DateTimeInterface::class), Mockery::type(Closure::class))
        ->andReturn($send);

    $innerRepository->shouldNotReceive('find');
    $cache->shouldNotReceive('forget');

    expect($repository->find($send->id))->toBe($send);
});

it('find queries the inner repository on a cache miss', function () {
    $send = SendFactory::make();

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $cache->shouldReceive('remember')
        ->once()
        ->with("send_{$send->id}", Mockery::type(DateTimeInterface::class), Mockery::type(Closure::class))
        ->andReturnUsing(fn (string $key, DateTimeInterface $ttl, Closure $callback) => $callback());

    $innerRepository->shouldReceive('find')->once()->with($send->id)->andReturn($send);

    expect($repository->find($send->id))->toBe($send);
});

it('find returns null and forgets the cache key when the inner repository has no send', function () {
    $sendId = (string) Str::ulid();

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $cache->shouldReceive('remember')
        ->once()
        ->with("send_{$sendId}", Mockery::type(DateTimeInterface::class), Mockery::type(Closure::class))
        ->andReturnUsing(fn (string $key, DateTimeInterface $ttl, Closure $callback) => $callback());

    $innerRepository->shouldReceive('find')->once()->with($sendId)->andReturnNull();
    $cache->shouldReceive('forget')->once()->with("send_{$sendId}");

    expect($repository->find($sendId))->toBeNull();
});

it('find forgets the cache key when the send is expired', function () {
    Carbon::setTestNow(now());
    config(['send.cache_ttl' => 60]);

    $send = SendFactory::make();
    $send->valid_to = now()->subMinute();

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $cache->shouldReceive('remember')
        ->once()
        ->with("send_{$send->id}", Mockery::type(DateTimeInterface::class), Mockery::type(Closure::class))
        ->andReturn($send);

    $cache->shouldReceive('forget')->once()->with("send_{$send->id}");
    $innerRepository->shouldNotReceive('find');

    expect($repository->find($send->id))->toBe($send);
});

it('find uses the configured ttl for remember()', function () {
    Carbon::setTestNow(now());
    config(['send.cache_ttl' => 60]);

    $send = SendFactory::make();
    $send->valid_to = now()->addMinutes(30);

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $cache->shouldReceive('remember')
        ->once()
        ->with(
            "send_{$send->id}",
            Mockery::on(fn (DateTimeInterface $expiresAt): bool => Carbon::parse($expiresAt)->equalTo(now()->addMinutes(60))),
            Mockery::type(Closure::class),
        )
        ->andReturnUsing(fn (string $key, DateTimeInterface $ttl, Closure $callback) => $callback());

    $innerRepository->shouldReceive('find')->once()->with($send->id)->andReturn($send);

    $repository->find($send->id);
});

it('findAll returns a cached collection without querying the inner repository', function () {
    $send = SendFactory::make(1, ['name' => 'Cached Send']);
    $userId = (string) $send->user_id;
    $columns = indexColumns();
    $cacheKey = sendsListCacheKey($userId, $columns);

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $cache->shouldReceive('remember')
        ->once()
        ->with($cacheKey, Mockery::type(DateTimeInterface::class), Mockery::type(Closure::class))
        ->andReturn(new Collection([$send]));

    $innerRepository->shouldNotReceive('findAll');
    $cache->shouldNotReceive('forget');

    $result = $repository->findAll($userId, $columns);

    expect($result)->toHaveCount(1)
        ->and($result->first())->toBe($send);
});

it('findAll queries the inner repository on a cache miss', function () {
    $send = SendFactory::make(1, ['name' => 'Fresh Send']);
    $userId = (string) $send->user_id;
    $columns = indexColumns();
    $cacheKey = sendsListCacheKey($userId, $columns);
    $collection = new Collection([$send]);

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $cache->shouldReceive('remember')
        ->once()
        ->with($cacheKey, Mockery::type(DateTimeInterface::class), Mockery::type(Closure::class))
        ->andReturnUsing(fn (string $key, DateTimeInterface $ttl, Closure $callback) => $callback());

    $innerRepository->shouldReceive('findAll')->once()->with($userId, $columns)->andReturn($collection);

    expect($repository->findAll($userId, $columns))->toBe($collection);
});

it('findAll forgets list cache when any send valid_to is in the past', function () {
    Carbon::setTestNow(now());
    config(['send.cache_ttl' => 60]);

    $expiredSend = SendFactory::make(1, ['name' => 'Expired Send']);
    $expiredSend->valid_to = now()->subMinute();
    $activeSend = SendFactory::make($expiredSend->user_id, ['name' => 'Active Send']);
    $activeSend->valid_to = now()->addDay();

    $userId = (string) $expiredSend->user_id;
    $columns = indexColumns();
    $cacheKey = sendsListCacheKey($userId, $columns);
    $collection = new Collection([$expiredSend, $activeSend]);

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $cache->shouldReceive('remember')
        ->once()
        ->with($cacheKey, Mockery::type(DateTimeInterface::class), Mockery::type(Closure::class))
        ->andReturn($collection);

    $cache->shouldReceive('forget')->once()->with($cacheKey);
    $innerRepository->shouldNotReceive('findAll');

    $repository->findAll($userId, $columns);
});

it('create stores the send in cache and invalidates the user send list cache', function () {
    $send = SendFactory::make();
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
        ->with("send_{$send->id}", $send, Mockery::type(DateTimeInterface::class));

    $cache->shouldReceive('forget')->once()->with("sends_{$userId}");
    $cache->shouldReceive('forget')->once()->with(sendsListCacheKey($userId, $columns));
    $cache->shouldReceive('forget')->once()->with("active_sends_count_{$userId}");

    expect($repository->create($sendData))->toBe($send);
});

it('countActiveForUser uses remember() with an expiration derived from send valid_to', function () {
    Carbon::setTestNow(now());
    config(['send.cache_ttl' => 60]);

    $userId = '42';
    $cacheKey = "active_sends_count_{$userId}";

    $send = SendFactory::make((int) $userId);
    $send->valid_to = now()->addMinutes(30);
    $collection = new Collection([$send]);

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $cache->shouldReceive('remember')
        ->once()
        ->with(sendsListCacheKey($userId, ['valid_to']), Mockery::type(DateTimeInterface::class), Mockery::type(Closure::class))
        ->andReturn($collection);

    $cache->shouldReceive('remember')
        ->once()
        ->with(
            $cacheKey,
            Mockery::on(fn (DateTimeInterface $expiresAt): bool => Carbon::parse($expiresAt)->equalTo(now()->addMinutes(30))),
            Mockery::type(Closure::class),
        )
        ->andReturnUsing(fn (string $key, DateTimeInterface $ttl, Closure $callback) => $callback());

    $innerRepository->shouldReceive('countActiveForUser')->once()->with($userId)->andReturn(1);

    expect($repository->countActiveForUser($userId))->toBe(1);
});

it('userHasActiveAuthorizedAccess caches the result from the inner repository', function () {
    Carbon::setTestNow(now());
    config(['send.cache_ttl' => 60]);

    $userId = '42';
    $sendId = (string) Str::ulid();
    $cacheKey = "active_authorized_access_{$userId}_{$sendId}";

    $send = SendFactory::make((int) $userId, ['id' => $sendId]);
    $send->valid_to = now()->addMinutes(30);

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $cache->shouldReceive('remember')
        ->once()
        ->with("send_{$sendId}", Mockery::type(DateTimeInterface::class), Mockery::type(Closure::class))
        ->andReturnUsing(fn (string $key, DateTimeInterface $ttl, Closure $callback) => $callback());

    $innerRepository->shouldReceive('find')->once()->with($sendId)->andReturn($send);

    $cache->shouldReceive('remember')
        ->once()
        ->with(
            $cacheKey,
            Mockery::on(fn (DateTimeInterface $expiresAt): bool => Carbon::parse($expiresAt)->equalTo(now()->addMinutes(30))),
            Mockery::type(Closure::class),
        )
        ->andReturnUsing(fn (string $key, DateTimeInterface $ttl, Closure $callback) => $callback());

    $innerRepository->shouldReceive('userHasActiveAuthorizedAccess')->once()->with($userId, $sendId)->andReturnTrue();

    expect($repository->userHasActiveAuthorizedAccess($userId, $sendId))->toBeTrue();
});
