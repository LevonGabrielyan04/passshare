<?php

use App\Models\User;

beforeEach(function () {
    config(['send.max_per_user' => 20]);

    $this->travel(61)->seconds();
});

function validSendPayload(User $viewer, string $name): array
{
    return [
        'name' => $name,
        'message' => fakeEncryptedMessage(),
        'expire_after' => '1 day',
        'viewers' => [$viewer->name],
    ];
}

it('throttles store requests after ten per minute', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();

    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($author)
            ->post(route('sends.store'), validSendPayload($viewer, "Send {$i}"))
            ->assertRedirect(route('dashboard'));
    }

    $this->actingAs($author)
        ->post(route('sends.store'), validSendPayload($viewer, 'Send 11'))
        ->assertTooManyRequests();
});

// it('throttles edit requests after ten per minute', function () {
//    $author = User::factory()->create();
//    $viewer = User::factory()->create();
//    $this->actingAs($author);
//
//    $send = app(SendServiceInterface::class)->createSend(validSendPayload($viewer, 'Editable Send'));
//
//    for ($i = 0; $i < 10; $i++) {
//        $this->actingAs($author)
//            ->get(route('sends.edit', $send))
//            ->assertOk();
//    }
//
//    $this->actingAs($author)
//        ->get(route('sends.edit', $send))
//        ->assertTooManyRequests();
// });
//
// it('throttles update requests after ten per minute', function () {
//    $author = User::factory()->create();
//    $viewer = User::factory()->create();
//    $this->actingAs($author);
//
//    $send = app(SendServiceInterface::class)->createSend(validSendPayload($viewer, 'Updatable Send'));
//
//    for ($i = 0; $i < 10; $i++) {
//        $response = $this->actingAs($author)
//            ->put(route('sends.update', $send), validSendPayload($viewer, "Updatable Send {$i}"));
//
//        expect($response->status())->not->toBe(429);
//    }
//
//    $this->actingAs($author)
//        ->put(route('sends.update', $send), validSendPayload($viewer, 'Updatable Send 11'))
//        ->assertTooManyRequests();
// });
