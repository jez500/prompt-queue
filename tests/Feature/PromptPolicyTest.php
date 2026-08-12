<?php

use App\Models\Project;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('an owner may update and delete their prompt', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->create();

    expect(Gate::forUser($user)->allows('update', $prompt))->toBeTrue()
        ->and(Gate::forUser($user)->allows('delete', $prompt))->toBeTrue();
});

test('a stranger is denied as not found', function () {
    $stranger = User::factory()->create();
    $prompt = Prompt::factory()->create();

    $response = Gate::forUser($stranger)->inspect('update', $prompt);

    expect($response->allowed())->toBeFalse()
        ->and($response->status())->toBe(404);
});

test('a stranger is denied as not found for projects', function () {
    $stranger = User::factory()->create();
    $project = Project::factory()->create();

    $response = Gate::forUser($stranger)->inspect('update', $project);

    expect($response->allowed())->toBeFalse()
        ->and($response->status())->toBe(404);
});
