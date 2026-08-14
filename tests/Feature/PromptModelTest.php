<?php

use App\Enums\PromptPriority;
use App\Enums\PromptStatus;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\Tag;
use App\Models\User;

test('display title falls back to the first line of the body', function () {
    $prompt = Prompt::factory()->create([
        'title' => null,
        'body' => "Refactor the billing service\n\nIt has grown to 900 lines.",
    ]);

    expect($prompt->displayTitle)->toBe('Refactor the billing service');
});

test('display title truncates a long first line', function () {
    $prompt = Prompt::factory()->create([
        'title' => null,
        'body' => str_repeat('a', 200),
    ]);

    expect(mb_strlen($prompt->displayTitle))->toBeLessThanOrEqual(80);
});

test('an explicit title wins over the body', function () {
    $prompt = Prompt::factory()->create([
        'title' => 'Billing refactor',
        'body' => "Something else entirely\nmore text",
    ]);

    expect($prompt->displayTitle)->toBe('Billing refactor');
});

test('in bucket scope separates inbox from projects', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create();

    Prompt::factory()->forUser($user)->create(['project_id' => null]);
    Prompt::factory()->forUser($user)->inProject($project)->create();

    expect(Prompt::query()->inBucket(null)->count())->toBe(1)
        ->and(Prompt::query()->inBucket($project->id)->count())->toBe(1);
});

test('search matches both title and body', function () {
    Prompt::factory()->create(['title' => 'Kafka consumer', 'body' => 'nothing here']);
    Prompt::factory()->create(['title' => null, 'body' => 'rewrite the kafka producer']);
    Prompt::factory()->create(['title' => 'Unrelated', 'body' => 'unrelated']);

    expect(Prompt::query()->search('kafka')->count())->toBe(2);
});

test('search treats a percent sign as literal text', function () {
    Prompt::factory()->create(['title' => null, 'body' => 'the discount is 100% off']);
    Prompt::factory()->create(['title' => null, 'body' => '100 percent complete']);

    expect(Prompt::query()->search('100%')->count())->toBe(1);
});

test('search treats an underscore as literal text', function () {
    Prompt::factory()->create(['title' => null, 'body' => 'read user_id from the token']);
    Prompt::factory()->create(['title' => null, 'body' => 'read userxid from the token']);

    expect(Prompt::query()->search('user_id')->count())->toBe(1);
});

test('search treats the escape character as literal text', function () {
    Prompt::factory()->create(['title' => null, 'body' => 'match a literal !% in the body']);
    Prompt::factory()->create(['title' => null, 'body' => 'unrelated entirely']);

    expect(Prompt::query()->search('!%')->count())->toBe(1);
});

test('status and priority scopes narrow the results', function () {
    Prompt::factory()->status(PromptStatus::Todo)->create(['priority' => PromptPriority::High]);
    Prompt::factory()->status(PromptStatus::Done)->create(['priority' => PromptPriority::Low]);

    expect(Prompt::query()->withStatuses(PromptStatus::open())->count())->toBe(1)
        ->and(Prompt::query()->withPriorities([PromptPriority::Low])->count())->toBe(1);
});

test('tag filtering requires every named tag', function () {
    $user = User::factory()->create();
    $bug = Tag::factory()->forUser($user)->create(['name' => 'bug']);
    $ui = Tag::factory()->forUser($user)->create(['name' => 'ui']);

    $both = Prompt::factory()->forUser($user)->create();
    $both->tags()->attach([$bug->id, $ui->id]);

    $onlyBug = Prompt::factory()->forUser($user)->create();
    $onlyBug->tags()->attach($bug->id);

    expect(Prompt::query()->withTagNames(['bug'])->count())->toBe(2)
        ->and(Prompt::query()->withTagNames(['bug', 'ui'])->count())->toBe(1);
});

test('scopes are no-ops when given nothing', function () {
    Prompt::factory()->count(3)->create();

    expect(Prompt::query()->search(null)->withStatuses([])->withPriorities([])->withTagNames([])->count())
        ->toBe(3);
});

test('the derived title strips markdown heading marks and leading space', function () {
    $prompt = Prompt::factory()->create([
        'title' => null,
        'body' => "##   Refactor the billing service\nIt has grown to 900 lines.",
    ]);

    expect($prompt->derivedTitle())->toBe('Refactor the billing service')
        ->and($prompt->displayTitle)->toBe('Refactor the billing service');
});

test('a title repeats the body when it is the first line, marks and all', function () {
    $derived = Prompt::factory()->create([
        'title' => 'Refactor the billing service',
        'body' => "# Refactor the billing service\nIt has grown to 900 lines.",
    ]);

    $own = Prompt::factory()->create([
        'title' => 'Billing refactor',
        'body' => "# Refactor the billing service\nIt has grown to 900 lines.",
    ]);

    expect($derived->titleRepeatsBody())->toBeTrue()
        ->and($own->titleRepeatsBody())->toBeFalse();
});

test('deleting a prompt keeps the row', function () {
    $prompt = Prompt::factory()->create();

    $prompt->delete();

    expect(Prompt::query()->count())->toBe(0)
        ->and(Prompt::withTrashed()->count())->toBe(1)
        ->and($prompt->fresh()?->deleted_at)->not->toBeNull();
});
