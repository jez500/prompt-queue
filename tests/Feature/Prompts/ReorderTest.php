<?php

use App\Models\Project;
use App\Models\Prompt;
use App\Models\User;

test('reordering rewrites positions to the given order', function () {
    $user = User::factory()->create();
    $first = Prompt::factory()->forUser($user)->atPosition(0)->create();
    $second = Prompt::factory()->forUser($user)->atPosition(1)->create();
    $third = Prompt::factory()->forUser($user)->atPosition(2)->create();

    $this->actingAs($user)
        ->patch(route('prompts.reorder'), [
            'project' => null,
            'ids' => [$third->id, $first->id, $second->id],
        ])
        ->assertRedirect();

    expect($third->refresh()->position)->toBe(0)
        ->and($first->refresh()->position)->toBe(1)
        ->and($second->refresh()->position)->toBe(2);
});

test('reordering works within a project bucket', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create();
    $a = Prompt::factory()->forUser($user)->inProject($project)->atPosition(0)->create();
    $b = Prompt::factory()->forUser($user)->inProject($project)->atPosition(1)->create();

    $this->actingAs($user)
        ->patch(route('prompts.reorder'), ['project' => $project->id, 'ids' => [$b->id, $a->id]])
        ->assertRedirect();

    expect($b->refresh()->position)->toBe(0)
        ->and($a->refresh()->position)->toBe(1);
});

test('an id from another bucket is rejected', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create();
    $inInbox = Prompt::factory()->forUser($user)->atPosition(0)->create();
    $inProject = Prompt::factory()->forUser($user)->inProject($project)->atPosition(0)->create();

    $this->actingAs($user)
        ->patch(route('prompts.reorder'), ['project' => null, 'ids' => [$inProject->id, $inInbox->id]])
        ->assertSessionHasErrors('ids');

    expect($inProject->refresh()->position)->toBe(0)
        ->and($inInbox->refresh()->position)->toBe(0);
});

test('another user\'s prompt id is rejected', function () {
    $user = User::factory()->create();
    $mine = Prompt::factory()->forUser($user)->atPosition(0)->create();
    $theirs = Prompt::factory()->atPosition(0)->create();

    $this->actingAs($user)
        ->patch(route('prompts.reorder'), ['project' => null, 'ids' => [$theirs->id, $mine->id]])
        ->assertSessionHasErrors('ids');

    expect($theirs->refresh()->position)->toBe(0)
        ->and($mine->refresh()->position)->toBe(0);
});

test('duplicate ids are rejected', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->atPosition(0)->create();

    $this->actingAs($user)
        ->patch(route('prompts.reorder'), ['project' => null, 'ids' => [$prompt->id, $prompt->id]])
        ->assertSessionHasErrors('ids.0');
});

test('an empty list is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('prompts.reorder'), ['project' => null, 'ids' => []])
        ->assertSessionHasErrors('ids');
});

test('guests cannot reorder', function () {
    $this->patch(route('prompts.reorder'), ['project' => null, 'ids' => [1]])
        ->assertRedirect(route('login'));
});
