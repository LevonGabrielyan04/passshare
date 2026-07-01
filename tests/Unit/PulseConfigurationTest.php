<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Pulse\Recorders\UserJobs;
use Laravel\Pulse\Recorders\UserRequests;
use Tests\TestCase;

uses(TestCase::class);

test('pulse disables user-specific recorders by default', function () {
    expect(config('pulse.recorders')[UserRequests::class]['enabled'])->toBeFalse()
        ->and(config('pulse.recorders')[UserJobs::class]['enabled'])->toBeFalse();
});

test('pulse uses the array cache driver by default', function () {
    expect(config('pulse.cache'))->toBe('array');
});

test('viewPulse gate allows the configured admin email when verified', function () {
    $user = User::factory()->make([
        'email' => config('pulse.admin_email'),
        'email_verified_at' => now(),
    ]);

    expect(Gate::forUser($user)->allows('viewPulse'))->toBeTrue();
});

test('viewPulse gate denies other users', function () {
    $user = User::factory()->make([
        'email' => 'other@example.com',
        'email_verified_at' => now(),
    ]);

    expect(Gate::forUser($user)->allows('viewPulse'))->toBeFalse();
});
