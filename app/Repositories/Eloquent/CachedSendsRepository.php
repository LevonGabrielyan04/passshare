<?php

namespace App\Repositories\Eloquent;

use App\DTOs\SendData;
use App\Models\Send;
use App\Repositories\Interfaces\SendRepositoryInterface;
use App\Support\SendIndexColumns;
use Carbon\CarbonInterface;
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

        $send = $this->cache->remember($cacheKey, $this->cacheTtl, fn (): ?Send => $this->repository->find($id));

        if (! $send instanceof Send) {
            $this->cache->forget($cacheKey);

            return null;
        }

        if (Carbon::parse($send->valid_to)->isPast()) {
            $this->cache->forget($cacheKey);
        }

        return $send;
    }

    /**
     * {@inheritDoc}
     */
    public function findAll(string $userId, array $columns): Collection
    {
        $cacheKey = $this->sendsCacheKey($userId, $columns);
        $ttl = now()->addMinutes($this->cacheTtl);

        $collection = $this->cache->remember(
            $cacheKey,
            $ttl,
            fn (): Collection => $this->repository->findAll($userId, $columns),
        );

        if (! $collection instanceof Collection) {
            $this->cache->forget($cacheKey);

            return new Collection;
        }

        foreach ($collection as $send) {
            if (! $send instanceof Send) {
                $this->cache->forget($cacheKey);

                return $this->repository->findAll($userId, $columns);
            }

            if (Carbon::parse($send->valid_to)->isPast()) {
                $this->cache->forget($cacheKey);
                break;
            }
        }

        return $collection;
    }

    /**
     * {@inheritDoc}
     */
    public function create(SendData $data, array $pivotData = []): Send
    {
        $send = $this->repository->create($data, $pivotData);
        $this->cache->put("send_{$send->id}", $send, $this->cacheExpiresAt($send));
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
        $this->cache->put("send_{$id}", $result, $this->cacheExpiresAt($result));
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
        $expiresAt = $this->cacheExpiresAtForActiveCount($userId);

        $count = $this->cache->remember(
            $cacheKey,
            $expiresAt,
            fn (): int => $this->repository->countActiveForUser($userId),
        );

        return is_int($count) ? $count : 0;
    }

    /**
     * {@inheritDoc}
     */
    public function userHasActiveAuthorizedAccess(string $userId, string $sendId): bool
    {
        $cacheKey = $this->activeAuthorizedAccessCacheKey($userId, $sendId);
        $send = $this->find($sendId);
        $expiresAt = $send !== null
            ? $this->cacheExpiresAt($send)
            : now()->addMinutes($this->cacheTtl);

        $hasAccess = $this->cache->remember(
            $cacheKey,
            $expiresAt,
            fn (): bool => $this->repository->userHasActiveAuthorizedAccess($userId, $sendId),
        );

        return is_bool($hasAccess) ? $hasAccess : false;
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
            if (! $send instanceof Send) {
                continue;
            }

            $expiresAt = $this->cacheExpiresAt($send)->min($expiresAt);
        }

        return $expiresAt;
    }

    private function cacheExpiresAtForActiveCount(string $userId): CarbonInterface
    {
        $expiresAt = now()->addMinutes($this->cacheTtl);

        foreach ($this->findAll($userId, ['valid_to']) as $send) {
            if (! $send instanceof Send) {
                continue;
            }

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
}
