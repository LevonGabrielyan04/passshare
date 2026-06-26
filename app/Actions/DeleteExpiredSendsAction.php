<?php

namespace App\Actions;

use App\Models\Send;

class DeleteExpiredSendsAction
{
    /**
     * Permanently delete all sends whose validity has expired.
     */
    public function execute(): int
    {
        return Send::query()
            ->where('valid_to', '<', now())
            ->delete();
    }
}
