<?php

use App\Models\Project;
use App\Models\User;

test('reordering rewrites positions to the given order', function () {
    $user = User::factory()->create();
    $first = Project::factory()->forUser($user)->atPosition(0)->create();
    $second = Project::factory()->forUser($user)->atPosition(1)->create();
    $third = Project::factory()->forUser($user)->atPosition(2)->create();

    $this->actingAs($user)
        ->patch(route('projects.reorder'), [
            'ids' => [$third->id, $first->id, $second->id],
        ])
        ->assertRedirect();

    expect($third->refresh()->position)->toBe(0)
        ->and($first->refresh()->position)->toBe(1)
        ->and($second->refresh()->position)->toBe(2);
});

test('another user\'s project is rejected', function () {
    $user = User::factory()->create();
    $mine = Project::factory()->forUser($user)->atPosition(0)->create();
    $theirs = Project::factory()->create();

    $this->actingAs($user)
        ->patch(route('projects.reorder'), ['ids' => [$theirs->id, $mine->id]])
        ->assertSessionHasErrors('ids');

    expect($mine->refresh()->position)->toBe(0)
        ->and($theirs->refresh()->position)->toBe(0);
});

test('a partial list is rejected rather than silently reordering the rest', function () {
    $user = User::factory()->create();
    $first = Project::factory()->forUser($user)->atPosition(0)->create();
    Project::factory()->forUser($user)->atPosition(1)->create();

    /* Accepting this would leave the sidebar showing an order the database
       does not have, which is worse than refusing the change. */
    $this->actingAs($user)
        ->patch(route('projects.reorder'), ['ids' => [$first->id]])
        ->assertSessionHasErrors('ids');

    expect($first->refresh()->position)->toBe(0);
});

test('reordering requires signing in', function () {
    $project = Project::factory()->create();

    $this->patch(route('projects.reorder'), ['ids' => [$project->id]])
        ->assertRedirect(route('login'));
});

test('a new project lands at the end of the order', function () {
    $user = User::factory()->create();
    Project::factory()->forUser($user)->atPosition(0)->create();
    Project::factory()->forUser($user)->atPosition(1)->create();

    $this->actingAs($user)
        ->post(route('projects.store'), ['name' => 'Later', 'color' => 'slate'])
        ->assertRedirect();

    expect(Project::query()->where('name', 'Later')->sole()->position)->toBe(2);
});

test('the first project a user makes starts the order', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.store'), ['name' => 'First', 'color' => 'slate'])
        ->assertRedirect();

    expect(Project::query()->where('name', 'First')->sole()->position)->toBe(0);
});

test('projects are shared in position order, not alphabetically', function () {
    $user = User::factory()->create();
    Project::factory()->forUser($user)->atPosition(0)->create(['name' => 'Zebra']);
    Project::factory()->forUser($user)->atPosition(1)->create(['name' => 'Alpha']);

    $this->actingAs($user)
        ->get(route('prompts.index'))
        ->assertInertia(
            fn ($page) => $page
                ->where('projects.0.name', 'Zebra')
                ->where('projects.1.name', 'Alpha')
        );
});
