<?php

namespace App\Services\Interfaces;

use App\Models\Send;
use Illuminate\Database\Eloquent\Collection;

interface SendReadServiceInterface
{
    /**
     * @return Collection<int, Send>
     */
    public function findAll(): Collection;

    public function findOne(Send $send): Send;

    public function countActiveForUser(int|string $userId): int;

    public function userHasActiveAuthorizedAccess(int|string $userId, string $sendId): bool;
}
