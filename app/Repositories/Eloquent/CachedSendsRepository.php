<?php

namespace App\Repositories\Eloquent;

use App\DTOs\SendData;
use App\Models\Send;
use App\Models\User;
use App\Repositories\Interfaces\SendRepositoryInterface;
use App\Support\SendIndexColumns;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
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

        if ($this->isSerializedSendPayload($cached)) {
            return $this->hydrateSend($cached);
        }

        if ($cached !== null) {
            $this->cache->forget($cacheKey);
        }

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

        if ($this->isSerializedCollectionPayload($cached)) {
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
        $this->forgetActiveSendsCount((string) $send->user_id);

        return $send;
    }

    /**
     * {@inheritDoc}
     */
    public function update(string $id, SendData $data, array $pivotData = []): Send
    {
        $sendBefore = $this->repository->find($id);
        $result = $this->repository->update($id, $data, $pivotData);
        $this->cache->put("send_{$id}", $this->serializeSend($result), $this->cacheExpiresAt($result));
        $this->forgetUserSends((string) $result->user_id, SendIndexColumns::COLUMNS);
        $this->forgetActiveSendsCount((string) $result->user_id);
        $this->forgetActiveAuthorizedAccessForSend($sendBefore);
        $this->forgetActiveAuthorizedAccessForSend($result);

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
            $this->forgetActiveSendsCount((string) $send->user_id);
            $this->forgetActiveAuthorizedAccessForSend($send);
        }

        return $this->repository->delete($id);
    }

    /**
     * {@inheritDoc}
     */
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
            $this->forgetActiveSendsCount((string) $send->user_id);
            $this->forgetActiveAuthorizedAccessForSend($this->repository->find($send->id));
        }

        if ($expired->isEmpty()) {
            return 0;
        }

        return $this->repository->deleteExpired();
    }

    /**
     * {@inheritDoc}
     */
    public function countActiveForUser(string $userId): int
    {
        $cacheKey = $this->activeSendsCountCacheKey($userId);
        $cached = $this->cache->get($cacheKey);

        if (is_int($cached)) {
            return $cached;
        }

        $count = $this->repository->countActiveForUser($userId);

        $this->cache->put(
            $cacheKey,
            $count,
            $this->cacheExpiresAtForActiveCount($userId),
        );

        return $count;
    }

    /**
     * {@inheritDoc}
     */
    public function userHasActiveAuthorizedAccess(string $userId, string $sendId): bool
    {
        $cacheKey = $this->activeAuthorizedAccessCacheKey($userId, $sendId);
        $cached = $this->cache->get($cacheKey);

        if (is_bool($cached)) {
            return $cached;
        }

        $hasAccess = $this->repository->userHasActiveAuthorizedAccess($userId, $sendId);
        $send = $this->find($sendId);
        $expiresAt = $send !== null
            ? $this->cacheExpiresAt($send)
            : now()->addMinutes($this->cacheTtl);

        $this->cache->put($cacheKey, $hasAccess, $expiresAt);

        return $hasAccess;
    }

    private function cacheExpiresAt(Send $send): CarbonInterface
    {
        $validTo = Carbon::parse($send->valid_to);
        $ttlLimit = now()->addMinutes($this->cacheTtl);

        if ($validTo->isPast()) {
            return now();
        }

        return $validTo->min($ttlLimit);
    }

    /**
     * @param  Collection<int, Send>  $collection
     */
    private function cacheExpiresAtForCollection(Collection $collection): CarbonInterface
    {
        $expiresAt = now()->addMinutes($this->cacheTtl);

        foreach ($collection as $send) {
            $expiresAt = $this->cacheExpiresAt($send)->min($expiresAt);
        }

        return $expiresAt;
    }

    private function cacheExpiresAtForActiveCount(string $userId): CarbonInterface
    {
        $expiresAt = now()->addMinutes($this->cacheTtl);

        foreach ($this->findAll($userId, ['valid_to']) as $send) {
            if (Carbon::parse($send->valid_to)->isPast()) {
                continue;
            }

            $expiresAt = $this->cacheExpiresAt($send)->min($expiresAt);
        }

        return $expiresAt;
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function sendsCacheKey(string $userId, array $columns): string
    {
        $encodedColumns = json_encode(array_values($columns));

        if ($encodedColumns === false) {
            throw new \RuntimeException('Unable to encode columns for cache key.');
        }

        return 'sends_'.$userId.'_'.hash('xxh128', $encodedColumns);
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function forgetUserSends(string $userId, array $columns): void
    {
        $this->cache->forget("sends_{$userId}");
        $this->cache->forget($this->sendsCacheKey($userId, $columns));
    }

    private function activeSendsCountCacheKey(string $userId): string
    {
        return "active_sends_count_{$userId}";
    }

    private function forgetActiveSendsCount(string $userId): void
    {
        $this->cache->forget($this->activeSendsCountCacheKey($userId));
    }

    private function activeAuthorizedAccessCacheKey(string $userId, string $sendId): string
    {
        return "active_authorized_access_{$userId}_{$sendId}";
    }

    private function forgetActiveAuthorizedAccess(string $userId, string $sendId): void
    {
        $this->cache->forget($this->activeAuthorizedAccessCacheKey($userId, $sendId));
    }

    private function forgetActiveAuthorizedAccessForSend(?Send $send): void
    {
        if ($send === null || ! $send->relationLoaded('authorizedUsers')) {
            return;
        }

        foreach ($send->authorizedUsers as $user) {
            $this->forgetActiveAuthorizedAccess((string) $user->id, $send->id);
        }
    }

    /**
     * @return array{attributes: array<string, mixed>, relations: array<string, list<array<string, mixed>>>}
     */
    private function serializeSend(Send $send): array
    {
        return [
            'attributes' => array_intersect_key(
                $send->getAttributes(),
                array_flip(SendIndexColumns::COLUMNS),
            ),
            'relations' => $this->serializeRelations($send),
        ];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function serializeRelations(Send $send): array
    {
        $relations = [];

        foreach ($send->getRelations() as $name => $relation) {
            if (! is_string($name) || ! $relation instanceof Collection) {
                continue;
            }

            $relations[$name] = array_values(
                $relation
                    ->map(fn (Model $model): array => $model->getAttributes())
                    ->all()
            );
        }

        return $relations;
    }

    /**
     * @param  Collection<int, Send>  $collection
     * @return array<int, array{attributes: array<string, mixed>, relations: array<string, list<array<string, mixed>>>}>
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

        foreach ($payload['relations'] as $name => $items) {
            if ($name === 'authorizedUsers') {
                $send->setRelation($name, User::hydrate($items));
            }
        }

        return $send;
    }

    /**
     * @param  array<int, array{attributes: array<string, mixed>, relations: array<string, list<array<string, mixed>>>}>  $payload
     * @return Collection<int, Send>
     */
    private function hydrateCollection(array $payload): Collection
    {
        return new Collection(
            array_map(fn (array $item) => $this->hydrateSend($item), $payload)
        );
    }

    /**
     * @return ($payload is array{attributes: array<string, mixed>, relations: array<string, list<array<string, mixed>>>} ? true : false)
     */
    private function isSerializedSendPayload(mixed $payload): bool
    {
        if (! is_array($payload)) {
            return false;
        }

        if (! isset($payload['attributes']) || ! is_array($payload['attributes'])) {
            return false;
        }

        if (! isset($payload['relations']) || ! is_array($payload['relations'])) {
            return false;
        }

        foreach ($payload['relations'] as $relation) {
            if (! is_array($relation)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return ($payload is list<array{attributes: array<string, mixed>, relations: array<string, list<array<string, mixed>>>}> ? true : false)
     */
    private function isSerializedCollectionPayload(mixed $payload): bool
    {
        if (! is_array($payload)) {
            return false;
        }

        foreach ($payload as $item) {
            if (! $this->isSerializedSendPayload($item)) {
                return false;
            }
        }

        return true;
    }
}
