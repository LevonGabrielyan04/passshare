<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pulseDashboardUrl(): string
{
    return route('pulse');
}

it('denies unauthenticated users access to pulse', function () {
    $this->get(pulseDashboardUrl())
        ->assertForbidden();
});

it('denies authenticated users with a different email access to pulse', function () {
    $user = User::factory()->create([
        'email' => 'other@example.com',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(pulseDashboardUrl())
        ->assertForbidden();
});

it('allows the configured verified admin email to access pulse', function () {
    $user = User::factory()->create([
        'email' => config('pulse.admin_email'),
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(pulseDashboardUrl())
        ->assertSuccessful();
});

it('denies the configured admin email when the user is not verified', function () {
    $user = User::factory()->unverified()->create([
        'email' => config('pulse.admin_email'),
    ]);

    $this->actingAs($user)
        ->get(pulseDashboardUrl())
        ->assertForbidden();
});
