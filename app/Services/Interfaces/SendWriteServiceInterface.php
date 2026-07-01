<?php

namespace App\Services\Interfaces;

use App\Models\Send;

interface SendWriteServiceInterface
{
    /**
     * Create a new secure send from the given request data.
     *
     * @param  array<string, mixed>  $data
     */
    public function createSend(array $data): Send;

    /**
     * Update an existing send by its ID with the given request data.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSend(string $id, array $data): Send|bool;

    /**
     * Delete a send by its ID.
     */
    public function deleteSend(string $id): bool;
}
