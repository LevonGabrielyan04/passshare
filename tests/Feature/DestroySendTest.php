<?php

use App\Models\User;
use App\Repositories\Interfaces\SendRepositoryInterface;
use App\Services\Interfaces\SendWriteServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(SendWriteServiceInterface::class);
    $this->repository = app(SendRepositoryInterface::class);
});

it('allows the owner to delete a send', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $this->actingAs($author);

    $send = $this->service->createSend([
        'name' => 'My Secret',
        'message' => 'top secret',
        'expire_after' => '1 day',
        'viewers' => [$viewer->name],
    ]);

    $this->actingAs($author)
        ->delete(route('sends.destroy', $send))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', 'Send deleted successfully.');

    expect($this->repository->find($send->id))->toBeNull();
});

it('forbids deleting a send for a non-owner', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $stranger = User::factory()->create();
    $this->actingAs($author);

    $send = $this->service->createSend([
        'name' => 'My Secret',
        'message' => 'top secret',
        'expire_after' => '1 day',
        'viewers' => [$viewer->name],
    ]);

    $this->actingAs($stranger)
        ->delete(route('sends.destroy', $send))
        ->assertNotFound();

    expect($this->repository->find($send->id))->not->toBeNull();
});

it('shows a delete button on the dashboard for sends the user owns', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $this->actingAs($author);

    $send = $this->service->createSend([
        'name' => 'My Secret',
        'message' => 'top secret',
        'expire_after' => '1 day',
        'viewers' => [$viewer->name],
    ]);

    $this->actingAs($author)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('sends.destroy', $send), false)
        ->assertSee('title="'.__('Delete').'"', false);
});
