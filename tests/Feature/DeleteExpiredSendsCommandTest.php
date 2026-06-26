<?php

use App\Models\Send;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;

it('deletes expired sends when the command is run', function () {
    $user = User::factory()->create();

    Send::forceCreate([
        'user_id' => $user->id,
        'message' => 'expired secret',
        'name' => 'Expired Send',
        'valid_to' => now()->subMinute(),
    ]);

    Send::forceCreate([
        'user_id' => $user->id,
        'message' => 'active secret',
        'name' => 'Active Send',
        'valid_to' => now()->addDay(),
    ]);

    $this->artisan('sends:delete-expired')
        ->expectsOutputToContain('Deleted 1 expired send(s).')
        ->assertSuccessful();

    expect(Send::query()->count())->toBe(1)
        ->and(Send::query()->where('name', 'Expired Send')->exists())->toBeFalse()
        ->and(Send::query()->where('name', 'Active Send')->exists())->toBeTrue();
});

it('schedules expired send deletion every thirty minutes', function () {
    $this->artisan('schedule:list');

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'sends:delete-expired'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/30 * * * *');
});
