<?php

use App\Enums\PromptStatus;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\Tag;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    // The `prompts/Index` Vue component is created in a later task; it has no
    // Vite manifest entry and no file on disk yet, so bypass both checks here.
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
});

test('a project can be created', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.store'), ['name' => 'Prompt Queue', 'color' => 'sky'])
        ->assertRedirect();

    expect($user->projects()->sole()->name)->toBe('Prompt Queue');
});

test('a duplicate project name is rejected for the same user', function () {
    $user = User::factory()->create();
    Project::factory()->forUser($user)->create(['name' => 'Prompt Queue']);

    $this->actingAs($user)
        ->post(route('projects.store'), ['name' => 'Prompt Queue', 'color' => 'sky'])
        ->assertSessionHasErrors('name');
});

test('an unknown colour is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.store'), ['name' => 'Fine', 'color' => 'chartreuse'])
        ->assertSessionHasErrors('color');
});

test('a project can be renamed', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->patch(route('projects.update', $project), ['name' => 'Renamed', 'color' => 'rose'])
        ->assertRedirect();

    expect($project->refresh()->name)->toBe('Renamed');
});

test('deleting a project moves its prompts to the inbox', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create();
    Prompt::factory()->forUser($user)->atPosition(0)->create(['body' => 'Already in inbox']);
    $moved = Prompt::factory()->forUser($user)->inProject($project)->atPosition(0)->create();

    $this->actingAs($user)->delete(route('projects.destroy', $project))->assertRedirect();

    expect(Project::query()->count())->toBe(0)
        ->and($moved->refresh()->project_id)->toBeNull()
        ->and($moved->position)->toBe(1);
});

test('another user\'s project is a 404', function () {
    $user = User::factory()->create();
    $foreign = Project::factory()->create();

    $this->actingAs($user)->patch(route('projects.update', $foreign), ['name' => 'Mine', 'color' => 'sky'])->assertNotFound();
    $this->actingAs($user)->delete(route('projects.destroy', $foreign))->assertNotFound();
});

test('projects and tags are shared with every authenticated page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create(['name' => 'Alpha']);
    $open = Prompt::factory()->forUser($user)->inProject($project)->status(PromptStatus::Todo)->create();
    Prompt::factory()->forUser($user)->inProject($project)->status(PromptStatus::Done)->create();
    /* Attached, because only tags that are on a prompt are shared — see the
       filter-bar case in Prompts/IndexTest. */
    $open->tags()->attach(Tag::factory()->forUser($user)->create(['name' => 'bug']));

    $this->actingAs($user)
        ->get(route('prompts.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('projects', 1)
            ->where('projects.0.name', 'Alpha')
            ->where('projects.0.openPromptsCount', 1)
            ->where('tags', ['bug'])
        );
});
