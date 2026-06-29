<?php

use App\Models\Send;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * @return array{name: string, message: string, expire_after: string, viewers: array<int, string>}
 */
function sendLimitPayload(User $viewer, string $name): array
{
    return [
        'name' => $name,
        'message' => fakeEncryptedMessage(),
        'expire_after' => '1 day',
        'viewers' => [$viewer->name],
    ];
}

function createSendForUser(User $user, ?CarbonInterface $validTo = null): Send
{
    return Send::forceCreate([
        'user_id' => $user->id,
        'message' => 'secret',
        'name' => 'Send-'.Str::random(5),
        'valid_to' => $validTo ?? now()->addDay(),
    ]);
}

it('allows creating sends while under the per-user limit', function () {
    config(['send.max_per_user' => 2]);

    $author = User::factory()->create();
    $viewer = User::factory()->create();

    createSendForUser($author);

    $this->actingAs($author)
        ->post(route('sends.store'), sendLimitPayload($viewer, 'Second Send'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasNoErrors();
});

it('denies the create form when the per-user limit is reached', function () {
    config(['send.max_per_user' => 2]);

    $author = User::factory()->create();

    createSendForUser($author);
    createSendForUser($author);

    $this->actingAs($author)
        ->get(route('sends.create'))
        ->assertForbidden();
});

it('denies storing a send when the per-user limit is reached', function () {
    config(['send.max_per_user' => 2]);

    $author = User::factory()->create();
    $viewer = User::factory()->create();

    createSendForUser($author);
    createSendForUser($author);

    $this->actingAs($author)
        ->post(route('sends.store'), sendLimitPayload($viewer, 'Over Limit Send'))
        ->assertForbidden();
});

it('does not count expired sends toward the per-user limit', function () {
    config(['send.max_per_user' => 2]);

    $author = User::factory()->create();
    $viewer = User::factory()->create();

    createSendForUser($author, now()->subMinute());
    createSendForUser($author);

    $this->actingAs($author)
        ->post(route('sends.store'), sendLimitPayload($viewer, 'Replacement Send'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasNoErrors();
});
