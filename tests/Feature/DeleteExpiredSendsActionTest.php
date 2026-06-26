<?php

use App\Actions\DeleteExpiredSendsAction;
use App\Models\Send;
use App\Models\User;

it('permanently deletes sends with an expired valid_to value', function () {
    $user = User::factory()->create();

    $expiredSend = Send::forceCreate([
        'user_id' => $user->id,
        'message' => 'expired secret',
        'name' => 'Expired Send',
        'valid_to' => now()->subMinute(),
    ]);

    $activeSend = Send::forceCreate([
        'user_id' => $user->id,
        'message' => 'active secret',
        'name' => 'Active Send',
        'valid_to' => now()->addDay(),
    ]);

    $deletedCount = app(DeleteExpiredSendsAction::class)->execute();

    expect($deletedCount)->toBe(1)
        ->and(Send::query()->count())->toBe(1)
        ->and(Send::query()->where('name', 'Expired Send')->exists())->toBeFalse()
        ->and(Send::query()->where('name', 'Active Send')->exists())->toBeTrue();
});

it('returns zero when no sends have expired', function () {
    $user = User::factory()->create();

    Send::forceCreate([
        'user_id' => $user->id,
        'message' => 'still valid',
        'name' => 'Active Send',
        'valid_to' => now()->addDay(),
    ]);

    $deletedCount = app(DeleteExpiredSendsAction::class)->execute();

    expect($deletedCount)->toBe(0)
        ->and(Send::query()->count())->toBe(1);
});
