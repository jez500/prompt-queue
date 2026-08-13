<?php

use App\Enums\PromptPriority;
use App\Enums\PromptStatus;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\Tag;
use App\Models\User;

test('a prompt can be retitled, rewritten and reprioritised', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->patch(route('prompts.update', $prompt), [
            'title' => 'Billing refactor',
            'body' => 'Split the service',
            'status' => 'done',
            'priority' => 'high',
        ])
        ->assertRedirect();

    $prompt->refresh();

    expect($prompt->title)->toBe('Billing refactor')
        ->and($prompt->body)->toBe('Split the service')
        ->and($prompt->status)->toBe(PromptStatus::Done)
        ->and($prompt->priority)->toBe(PromptPriority::High);
});

test('a body-only update leaves every other field alone', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create();
    $tag = Tag::factory()->forUser($user)->create(['name' => 'bug']);
    $prompt = Prompt::factory()->forUser($user)->inProject($project)->create([
        'title' => 'Keep me',
        'status' => PromptStatus::Implementing,
        'priority' => PromptPriority::High,
    ]);
    $prompt->tags()->attach($tag);

    $this->actingAs($user)
        ->patch(route('prompts.update', $prompt), ['body' => 'Only the body changed'])
        ->assertSessionHasNoErrors();

    $prompt->refresh();

    expect($prompt->body)->toBe('Only the body changed')
        ->and($prompt->title)->toBe('Keep me')
        ->and($prompt->status)->toBe(PromptStatus::Implementing)
        ->and($prompt->priority)->toBe(PromptPriority::High)
        ->and($prompt->project_id)->toBe($project->id)
        ->and($prompt->tags->pluck('name')->all())->toBe(['bug']);
});

test('omitting the project leaves the prompt where it is', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create();
    $prompt = Prompt::factory()->forUser($user)->inProject($project)->atPosition(2)->create();

    $this->actingAs($user)->patch(route('prompts.update', $prompt), ['body' => 'Changed']);

    expect($prompt->refresh()->project_id)->toBe($project->id)
        ->and($prompt->position)->toBe(2);
});

test('sending a null project moves the prompt to the inbox', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create();
    $prompt = Prompt::factory()->forUser($user)->inProject($project)->create();

    $this->actingAs($user)->patch(route('prompts.update', $prompt), [
        'body' => $prompt->body,
        'project' => null,
    ]);

    expect($prompt->refresh()->project_id)->toBeNull();
});

test('a tags-only update leaves the body alone', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->create(['body' => 'Untouched body']);

    $this->actingAs($user)
        ->patch(route('prompts.update', $prompt), ['tags' => ['refactor']])
        ->assertSessionHasNoErrors();

    $prompt->refresh();

    expect($prompt->body)->toBe('Untouched body')
        ->and($prompt->tags->pluck('name')->all())->toBe(['refactor']);
});

test('omitting tags leaves the existing ones attached', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->forUser($user)->create(['name' => 'bug']);
    $prompt = Prompt::factory()->forUser($user)->create();
    $prompt->tags()->attach($tag);

    $this->actingAs($user)->patch(route('prompts.update', $prompt), ['body' => 'Changed']);

    expect($prompt->refresh()->tags->pluck('name')->all())->toBe(['bug']);
});

test('sending an empty tag list detaches every tag', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->forUser($user)->create(['name' => 'bug']);
    $prompt = Prompt::factory()->forUser($user)->create();
    $prompt->tags()->attach($tag);

    $this->actingAs($user)->patch(route('prompts.update', $prompt), ['tags' => []]);

    expect($prompt->refresh()->tags)->toHaveCount(0);
});

test('a body sent as an empty string is still rejected', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->create(['body' => 'Original']);

    $this->actingAs($user)
        ->patch(route('prompts.update', $prompt), ['body' => '   '])
        ->assertSessionHasErrors('body');

    expect($prompt->refresh()->body)->toBe('Original');
});

test('clearing the title restores the body fallback', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->create([
        'title' => 'Explicit',
        'body' => "Derived line\nrest",
    ]);

    $this->actingAs($user)->patch(route('prompts.update', $prompt), [
        'title' => null,
        'body' => "Derived line\nrest",
    ]);

    expect($prompt->refresh()->displayTitle)->toBe('Derived line');
});

test('tags are created for the user and synced', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->create();

    $this->actingAs($user)->patch(route('prompts.update', $prompt), [
        'body' => $prompt->body,
        'tags' => ['refactor', 'bug'],
    ]);

    expect($prompt->refresh()->tags->pluck('name')->sort()->values()->all())->toBe(['bug', 'refactor'])
        ->and(Tag::query()->where('user_id', $user->id)->count())->toBe(2);
});

test('an existing tag is reused rather than duplicated', function () {
    $user = User::factory()->create();
    Tag::factory()->forUser($user)->create(['name' => 'bug']);
    $prompt = Prompt::factory()->forUser($user)->create();

    $this->actingAs($user)->patch(route('prompts.update', $prompt), [
        'body' => $prompt->body,
        'tags' => ['bug'],
    ]);

    expect(Tag::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('moving a prompt to another project puts it at the top of that bucket', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create();
    $sitting = Prompt::factory()->forUser($user)->inProject($project)->atPosition(0)->create();
    $moving = Prompt::factory()->forUser($user)->atPosition(0)->create();

    $this->actingAs($user)->patch(route('prompts.update', $moving), [
        'body' => $moving->body,
        'project' => $project->id,
    ]);

    expect($moving->refresh()->position)->toBe(0)
        ->and($moving->project_id)->toBe($project->id)
        ->and($sitting->refresh()->position)->toBe(1);
});

test('staying in the same project leaves the position alone', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->atPosition(3)->create();

    $this->actingAs($user)->patch(route('prompts.update', $prompt), ['body' => 'Changed']);

    expect($prompt->refresh()->position)->toBe(3);
});

test('a prompt can be deleted', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->create();

    $this->actingAs($user)->delete(route('prompts.destroy', $prompt))->assertRedirect();

    expect(Prompt::query()->count())->toBe(0);
});

test('another user\'s prompt is a 404 for update and delete', function () {
    $user = User::factory()->create();
    $foreign = Prompt::factory()->create();

    $this->actingAs($user)->patch(route('prompts.update', $foreign), ['body' => 'Mine now'])->assertNotFound();
    $this->actingAs($user)->delete(route('prompts.destroy', $foreign))->assertNotFound();

    expect($foreign->refresh()->body)->not->toBe('Mine now');
});

test('a prompt cannot be moved into another user\'s project', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->create();
    $foreignProject = Project::factory()->create();

    $this->actingAs($user)
        ->patch(route('prompts.update', $prompt), ['body' => $prompt->body, 'project' => $foreignProject->id])
        ->assertSessionHasErrors('project');
});
