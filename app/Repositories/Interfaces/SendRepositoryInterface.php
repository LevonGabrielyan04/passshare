<?php

namespace App\Repositories\Interfaces;

use App\DTOs\SendData;
use App\Models\Send;
use Illuminate\Database\Eloquent\Collection;

interface SendRepositoryInterface
{
    /**
     * Find a specific record by its ID.
     */
    public function find(string $id): ?Send;

    /**
     * Find all records created by the given user.
     *
     * @param  array<int, string>  $columns
     * @return Collection<int, Send>
     */
    public function findAll(string $userId, array $columns): Collection;

    /**
     * Create a new record.
     *
     * @param  array<int, array{send_id: string, user_id: int}>  $pivotData
     */
    public function create(SendData $data, array $pivotData = []): Send;

    /**
     * Update an existing record by its ID.
     *
     * @param  array<int, array{send_id: string, user_id: int}>  $pivotData
     * @return Send
     */
    public function update(string $id, SendData $data, array $pivotData = []);

    /**
     * Delete a record by its ID.
     */
    public function delete(string $id): bool;

    /**
     * Find sends whose validity has expired.
     *
     * @return Collection<int, Send>
     */
    public function findExpired(): Collection;

    /**
     * Permanently delete all sends whose validity has expired.
     */
    public function deleteExpired(): int;

    /**
     * Count sends that are still valid for the given user.
     */
    public function countActiveForUser(string $userId): int;

    /**
     * Determine whether the user is authorized to view the given active send.
     */
    public function userHasActiveAuthorizedAccess(string $userId, string $sendId): bool;
}
