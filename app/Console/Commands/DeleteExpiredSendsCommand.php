<?php

namespace App\Console\Commands;

use App\Actions\DeleteExpiredSendsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sends:delete-expired')]
#[Description('Permanently delete all sends that have expired')]
class DeleteExpiredSendsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DeleteExpiredSendsAction $action): int
    {
        $deletedCount = $action->execute();

        $this->info("Deleted {$deletedCount} expired send(s).");

        return self::SUCCESS;
    }
}
