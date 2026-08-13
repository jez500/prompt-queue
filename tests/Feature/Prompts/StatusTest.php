<?php

use App\Enums\PromptStatus;
use App\Models\Prompt;
use App\Models\User;

test('copying a todo prompt advances it to implementing', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->status(PromptStatus::Todo)->create();

    $this->actingAs($user)->patch(route('prompts.status', $prompt))->assertRedirect();

    expect($prompt->refresh()->status)->toBe(PromptStatus::Implementing);
});

test('copying an implementing prompt changes nothing', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->status(PromptStatus::Implementing)->create();

    $this->actingAs($user)->patch(route('prompts.status', $prompt));

    expect($prompt->refresh()->status)->toBe(PromptStatus::Implementing);
});

test('copying a done prompt does not resurrect it', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->status(PromptStatus::Done)->create();

    $this->actingAs($user)->patch(route('prompts.status', $prompt));

    expect($prompt->refresh()->status)->toBe(PromptStatus::Done);
});

test('status can be set directly', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->status(PromptStatus::Todo)->create();

    $this->actingAs($user)
        ->patch(route('prompts.status', $prompt), ['status' => PromptStatus::Done->value])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($prompt->refresh()->status)->toBe(PromptStatus::Done);
});

test('status must be valid when supplied', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->status(PromptStatus::Todo)->create();

    $this->actingAs($user)
        ->patch(route('prompts.status', $prompt), ['status' => 'blocked'])
        ->assertSessionHasErrors('status');

    expect($prompt->refresh()->status)->toBe(PromptStatus::Todo);
});

test('another user\'s prompt is a 404', function () {
    $user = User::factory()->create();
    $foreign = Prompt::factory()->status(PromptStatus::Todo)->create();

    $this->actingAs($user)->patch(route('prompts.status', $foreign))->assertNotFound();

    expect($foreign->refresh()->status)->toBe(PromptStatus::Todo);
});
