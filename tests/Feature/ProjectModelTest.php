<?php

use App\Enums\ProjectColor;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\QueryException;

test('a project belongs to a user and casts its colour', function () {
    $user = User::factory()->create();

    $project = Project::factory()->forUser($user)->create(['color' => 'sky']);

    expect($project->user->is($user))->toBeTrue()
        ->and($project->color)->toBe(ProjectColor::Sky)
        ->and($user->projects()->count())->toBe(1);
});

test('a user cannot have two projects with the same name', function () {
    $user = User::factory()->create();

    Project::factory()->forUser($user)->create(['name' => 'Prompt Queue']);

    expect(fn () => Project::factory()->forUser($user)->create(['name' => 'Prompt Queue']))
        ->toThrow(QueryException::class);
});

test('two users may each have a project with the same name', function () {
    Project::factory()->forUser(User::factory()->create())->create(['name' => 'Shared']);
    Project::factory()->forUser(User::factory()->create())->create(['name' => 'Shared']);

    expect(Project::query()->where('name', 'Shared')->count())->toBe(2);
});
