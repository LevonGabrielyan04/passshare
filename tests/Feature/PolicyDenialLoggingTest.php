<?php

use App\Models\User;
use App\Services\Interfaces\SendWriteServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    $this->service = app(SendWriteServiceInterface::class);
});

/**
 * @param  array<int, MessageLogged>  $logs
 */
function listenForPolicyDenialLogs(array &$logs): void
{
    Log::listen(function (MessageLogged $event) use (&$logs) {
        if ($event->message === 'Policy authorization denied') {
            $logs[] = $event;
        }
    });
}

/**
 * @return array{name: string, message: string, expire_after: string, viewers: array<int, string>}
 */
function unauthorizedSendPayload(): array
{
    return [
        'name' => 'My Secret',
        'message' => 'top secret',
        'expire_after' => '1 day',
        'viewers' => [],
    ];
}

it('does not log policy denials when logging is disabled', function () {
    config(['policy.denial_logging.enabled' => false]);

    $logs = [];
    listenForPolicyDenialLogs($logs);

    $author = User::factory()->create();
    $stranger = User::factory()->create();
    $this->actingAs($author);

    $send = $this->service->createSend(unauthorizedSendPayload());

    $this->actingAs($stranger)
        ->get(route('sends.show', $send))
        ->assertNotFound();

    expect($logs)->toBeEmpty();
});

it('throttles excessive policy denial logs per minute', function () {
    config(['policy.denial_logging.max_per_minute' => 2]);

    $logs = [];
    listenForPolicyDenialLogs($logs);

    $author = User::factory()->create();
    $stranger = User::factory()->create();
    $this->actingAs($author);

    foreach (range(1, 3) as $attempt) {
        $this->actingAs($author);

        $send = $this->service->createSend([
            ...unauthorizedSendPayload(),
            'name' => "Secret {$attempt}",
        ]);

        $this->actingAs($stranger)
            ->get(route('sends.show', $send))
            ->assertNotFound();
    }

    expect($logs)->toHaveCount(2);
});

it('throttles policy denial logs per user when configured', function () {
    config([
        'policy.denial_logging.max_per_minute' => 1,
        'policy.denial_logging.throttle_by' => 'user',
    ]);

    $logs = [];
    listenForPolicyDenialLogs($logs);

    $author = User::factory()->create();
    $firstStranger = User::factory()->create();
    $secondStranger = User::factory()->create();
    $this->actingAs($author);

    $firstSend = $this->service->createSend([
        ...unauthorizedSendPayload(),
        'name' => 'First Secret',
    ]);

    $secondSend = $this->service->createSend([
        ...unauthorizedSendPayload(),
        'name' => 'Second Secret',
    ]);

    $this->actingAs($firstStranger)
        ->get(route('sends.show', $firstSend))
        ->assertNotFound();

    $this->actingAs($firstStranger)
        ->get(route('sends.show', $secondSend))
        ->assertNotFound();

    $this->actingAs($secondStranger)
        ->get(route('sends.show', $firstSend))
        ->assertNotFound();

    expect($logs)->toHaveCount(2);
});

it('throttles policy denial logs globally across distinct sources', function () {
    config([
        'policy.denial_logging.max_per_minute' => 100,
        'policy.denial_logging.global_max_per_minute' => 2,
    ]);

    $logs = [];
    listenForPolicyDenialLogs($logs);

    $author = User::factory()->create();
    $stranger = User::factory()->create();

    foreach (['1.1.1.1', '2.2.2.2', '3.3.3.3'] as $index => $ip) {
        $this->actingAs($author);

        $send = $this->service->createSend([
            ...unauthorizedSendPayload(),
            'name' => 'Secret '.($index + 1),
        ]);

        $this->actingAs($stranger)
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->get(route('sends.show', $send))
            ->assertNotFound();
    }

    expect($logs)->toHaveCount(2);
});
