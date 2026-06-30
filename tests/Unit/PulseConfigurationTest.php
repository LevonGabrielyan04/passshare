<?php

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
