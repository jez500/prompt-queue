<?php

use App\Enums\PromptPriority;
use App\Enums\PromptStatus;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\Tag;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('the index renders the workbench with open prompts only', function () {
    $user = User::factory()->create();
    Prompt::factory()->forUser($user)->status(PromptStatus::Todo)->create(['body' => 'Open one']);
    Prompt::factory()->forUser($user)->status(PromptStatus::Done)->create(['body' => 'Finished']);

    $this->actingAs($user)
        ->get(route('prompts.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('prompts/Index')
            ->has('prompts', 1)
            ->where('prompts.0.title', 'Open one')
        );
});

test('done prompts appear when asked for', function () {
    $user = User::factory()->create();
    Prompt::factory()->forUser($user)->status(PromptStatus::Done)->create();

    $this->actingAs($user)
        ->get(route('prompts.index', ['status' => ['done']]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('prompts', 1));
});

test('prompts are returned in position order', function () {
    $user = User::factory()->create();
    Prompt::factory()->forUser($user)->atPosition(1)->create(['body' => 'Second']);
    Prompt::factory()->forUser($user)->atPosition(0)->create(['body' => 'First']);

    $this->actingAs($user)
        ->get(route('prompts.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('prompts.0.title', 'First')
            ->where('prompts.1.title', 'Second')
        );
});

test('search matches title and body', function () {
    $user = User::factory()->create();
    Prompt::factory()->forUser($user)->create(['title' => 'Kafka consumer', 'body' => 'x']);
    Prompt::factory()->forUser($user)->create(['title' => null, 'body' => 'rewrite the kafka producer']);
    Prompt::factory()->forUser($user)->create(['title' => null, 'body' => 'unrelated']);

    $this->actingAs($user)
        ->get(route('prompts.index', ['q' => 'kafka']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('prompts', 2));
});

test('a project filter shows only that project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create();
    Prompt::factory()->forUser($user)->inProject($project)->create(['body' => 'In project']);
    Prompt::factory()->forUser($user)->create(['body' => 'In inbox']);

    $this->actingAs($user)
        ->get(route('prompts.index', ['project' => $project->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('prompts', 1)
            ->where('prompts.0.title', 'In project')
            ->where('prompts.0.projectName', $project->name)
        );

    $this->actingAs($user)
        ->get(route('prompts.index', ['project' => 'inbox']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('prompts', 1)
            ->where('prompts.0.title', 'In inbox')
        );
});

test('priority and tag filters narrow the list', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->forUser($user)->create(['name' => 'bug']);
    $tagged = Prompt::factory()->forUser($user)->create(['priority' => PromptPriority::High]);
    $tagged->tags()->attach($tag);
    Prompt::factory()->forUser($user)->create(['priority' => PromptPriority::Low]);

    $this->actingAs($user)
        ->get(route('prompts.index', ['priority' => ['high']]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('prompts', 1));

    $this->actingAs($user)
        ->get(route('prompts.index', ['tags' => ['bug']]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('prompts', 1));
});

test('another user\'s prompts are never listed', function () {
    $user = User::factory()->create();
    Prompt::factory()->create(['body' => 'Not yours']);

    $this->actingAs($user)
        ->get(route('prompts.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('prompts', 0));
});

test('reordering is allowed only in a single unfiltered bucket', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create();

    $assertCanReorder = function (array $query, bool $expected) use ($user) {
        $this->actingAs($user)
            ->get(route('prompts.index', $query))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canReorder', $expected));
    };

    $assertCanReorder(['project' => $project->id], true);
    $assertCanReorder(['project' => 'inbox'], true);
    $assertCanReorder([], false);
    $assertCanReorder(['project' => $project->id, 'q' => 'kafka'], false);
    $assertCanReorder(['project' => $project->id, 'status' => ['done']], false);
    $assertCanReorder(['project' => $project->id, 'priority' => ['high']], false);
    $assertCanReorder(['project' => $project->id, 'tags' => ['bug']], false);
});

test('an unknown status is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('prompts.index', ['status' => ['banana']]))
        ->assertSessionHasErrors('status.0');
});

test('guests are redirected to login', function () {
    $this->get(route('prompts.index'))->assertRedirect(route('login'));
});
