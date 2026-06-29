<?php

namespace App\Repositories\Eloquent;

use App\DTOs\SendData;
use App\Models\Send;
use App\Models\User;
use App\Repositories\Interfaces\SendRepositoryInterface;
use App\Support\SendIndexColumns;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

readonly class CachedSendsRepository implements SendRepositoryInterface
{
    private readonly int $cacheTtl;

    public function __construct(
        private SendRepositoryInterface $repository,
        private CacheRepository $cache
    ) {
        $this->cacheTtl = config('send.cache_ttl');
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $id): ?Send
    {
        $cacheKey = "send_{$id}";
        $cached = $this->cache->get($cacheKey);

        if (is_array($cached)) {
            return $this->hydrateSend($cached);
        }

        if ($cached !== null) {
            $this->cache->forget($cacheKey);
        }

        /** @var Send $send */
        $send = $this->repository->find($id);

        if ($send !== null) {
            $this->cache->put($cacheKey, $this->serializeSend($send), $this->cacheExpiresAt($send));
        }

        return $send;
    }

    /**
     * {@inheritDoc}
     */
    public function findAll(string $userId, array $columns): Collection
    {
        $cacheKey = $this->sendsCacheKey($userId, $columns);
        $cached = $this->cache->get($cacheKey);

        if (is_array($cached)) {
            return $this->hydrateCollection($cached);
        }

        if ($cached !== null) {
            $this->cache->forget($cacheKey);
        }

        $collection = $this->repository->findAll($userId, $columns);

        $this->cache->put(
            $cacheKey,
            $this->serializeCollection($collection),
            $this->cacheExpiresAtForCollection($collection)
        );

        return $collection;
    }

    /**
     * {@inheritDoc}
     */
    public function create(SendData $data, array $pivotData = []): Send
    {
        $send = $this->repository->create($data, $pivotData);
        $this->cache->put("send_{$send->id}", $this->serializeSend($send), $this->cacheExpiresAt($send));
        $this->forgetUserSends((string) $send->user_id, SendIndexColumns::COLUMNS);

        return $send;
    }

    /**
     * {@inheritDoc}
     */
    public function update(string $id, SendData $data, array $pivotData = []): Send
    {
        $result = $this->repository->update($id, $data, $pivotData);
        $this->cache->put("send_{$id}", $this->serializeSend($result), $this->cacheExpiresAt($result));
        $this->forgetUserSends((string) $result->user_id, SendIndexColumns::COLUMNS);

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $id): bool
    {
        $send = $this->repository->find($id);
        $this->cache->forget("send_{$id}");

        if ($send !== null) {
            $this->forgetUserSends((string) $send->user_id, SendIndexColumns::COLUMNS);
        }

        return $this->repository->delete($id);
    }

    public function findExpired(): Collection
    {
        return $this->repository->findExpired();
    }

    public function deleteExpired(): int
    {
        $expired = $this->repository->findExpired();

        foreach ($expired as $send) {
            $this->cache->forget("send_{$send->id}");
            $this->forgetUserSends((string) $send->user_id, SendIndexColumns::COLUMNS);
        }

        if ($expired->isEmpty()) {
            return 0;
        }

        return $this->repository->deleteExpired();
    }

    private function cacheExpiresAt(Send $send): \DateTimeInterface
    {
        $validTo = Carbon::parse($send->valid_to);
        $ttlLimit = now()->addMinutes($this->cacheTtl);

        if ($validTo->isPast()) {
            return now();
        }

        return $validTo->min($ttlLimit);
    }

    private function cacheExpiresAtForCollection(Collection $collection): \DateTimeInterface
    {
        $expiresAt = now()->addMinutes($this->cacheTtl);

        foreach ($collection as $send) {
            $expiresAt = $this->cacheExpiresAt($send)->min($expiresAt);
        }

        return $expiresAt;
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function sendsCacheKey(string $userId, array $columns): string
    {
        return 'sends_'.$userId.'_'.hash('xxh128', json_encode(array_values($columns)));
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function forgetUserSends(string $userId, array $columns): void
    {
        $this->cache->forget("sends_{$userId}");
        $this->cache->forget($this->sendsCacheKey($userId, $columns));
    }

    /**
     * @return array{attributes: array<string, mixed>, relations: array<string, list<array<string, mixed>>>}
     */
    private function serializeSend(Send $send): array
    {
        $payload = [
            'attributes' => array_intersect_key(
                $send->getAttributes(),
                array_flip(SendIndexColumns::COLUMNS),
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

    /**
     * @return list<array{attributes: array<string, mixed>, relations: array<string, list<array<string, mixed>>>}>
     */
    private function serializeCollection(Collection $collection): array
    {
        return $collection
            ->map(fn (Send $send) => $this->serializeSend($send))
            ->all();
    }

    /**
     * @param  array{attributes: array<string, mixed>, relations: array<string, list<array<string, mixed>>>}  $payload
     */
    private function hydrateSend(array $payload): Send
    {
        $send = (new Send)->newFromBuilder($payload['attributes']);

        foreach ($payload['relations'] ?? [] as $name => $items) {
            if ($name === 'authorizedUsers') {
                $send->setRelation($name, User::hydrate($items));
            }
        }

        return $send;
    }

    /**
     * @param  list<array{attributes: array<string, mixed>, relations: array<string, list<array<string, mixed>>>}>  $payload
     */
    private function hydrateCollection(array $payload): Collection
    {
        return new Collection(
            array_map(fn (array $item) => $this->hydrateSend($item), $payload)
        );
    }
}
