<?php

use App\Enums\PromptPriority;
use App\Enums\PromptStatus;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\User;

test('capturing with only a body creates an inbox prompt', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('prompts.store'), ['body' => "Refactor billing\n\nIt is 900 lines."])
        ->assertRedirect();

    $prompt = Prompt::query()->sole();

    expect($prompt->user_id)->toBe($user->id)
        ->and($prompt->project_id)->toBeNull()
        ->and($prompt->title)->toBeNull()
        ->and($prompt->status)->toBe(PromptStatus::Todo)
        ->and($prompt->priority)->toBe(PromptPriority::Normal)
        ->and($prompt->position)->toBe(0)
        ->and($prompt->displayTitle)->toBe('Refactor billing');
});

test('capturing into a project files it there', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->post(route('prompts.store'), ['body' => 'Add tests', 'project' => $project->id])
        ->assertRedirect();

    expect(Prompt::query()->sole()->project_id)->toBe($project->id);
});

test('a new prompt goes to the top and pushes the bucket down', function () {
    $user = User::factory()->create();
    $existing = Prompt::factory()->forUser($user)->atPosition(0)->create();

    $this->actingAs($user)->post(route('prompts.store'), ['body' => 'Newest thought']);

    expect($existing->refresh()->position)->toBe(1)
        ->and(Prompt::query()->where('body', 'Newest thought')->sole()->position)->toBe(0);
});

test('positions in another bucket are untouched', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create();
    $elsewhere = Prompt::factory()->forUser($user)->inProject($project)->atPosition(0)->create();

    $this->actingAs($user)->post(route('prompts.store'), ['body' => 'Inbox thought']);

    expect($elsewhere->refresh()->position)->toBe(0);
});

test('a body is required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('prompts.store'), ['body' => '   '])
        ->assertSessionHasErrors('body');
});

test('a prompt cannot be captured into another user\'s project', function () {
    $user = User::factory()->create();
    $foreign = Project::factory()->create();

    $this->actingAs($user)
        ->post(route('prompts.store'), ['body' => 'Sneaky', 'project' => $foreign->id])
        ->assertSessionHasErrors('project');
});

test('guests cannot capture', function () {
    $this->post(route('prompts.store'), ['body' => 'Hello'])->assertRedirect(route('login'));
});
