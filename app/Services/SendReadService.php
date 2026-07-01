<?php

namespace App\Services;

use App\Models\Send;
use App\Repositories\Interfaces\SendRepositoryInterface;
use App\Services\Interfaces\SendReadServiceInterface;
use App\Support\SendIndexColumns;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SendReadService implements SendReadServiceInterface
{
    public function __construct(protected SendRepositoryInterface $sendRepository) {}

    /**
     * {@inheritDoc}
     */
    public function findAll(): Collection
    {
        $userId = auth()->id();

        return $this->sendRepository->findAll((string) $userId, SendIndexColumns::COLUMNS);
    }

    public function findOne(Send $send): Send
    {
        return DB::transaction(function () use ($send) {
            $send->loadMissing('authorizedUsers');

            return $send;
        });
    }

    public function countActiveForUser(int|string $userId): int
    {
        return $this->sendRepository->countActiveForUser((string) $userId);
    }

    public function userHasActiveAuthorizedAccess(int|string $userId, string $sendId): bool
    {
        return $this->sendRepository->userHasActiveAuthorizedAccess((string) $userId, $sendId);
    }
}
