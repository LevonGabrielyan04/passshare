<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

uses(TestCase::class);

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
