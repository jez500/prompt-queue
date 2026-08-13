<?php

use App\Enums\PromptPriority;
use App\Models\Prompt;
use App\Models\User;

test('priority can be set directly', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->priority(PromptPriority::Low)->create();

    $this->actingAs($user)
        ->patch(route('prompts.priority', $prompt), ['priority' => PromptPriority::High->value])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($prompt->refresh()->priority)->toBe(PromptPriority::High);
});

test('priority must be valid', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->priority(PromptPriority::Normal)->create();

    $this->actingAs($user)
        ->patch(route('prompts.priority', $prompt), ['priority' => 'urgent'])
        ->assertSessionHasErrors('priority');

    expect($prompt->refresh()->priority)->toBe(PromptPriority::Normal);
});

test('another user prompt priority is a 404', function () {
    $user = User::factory()->create();
    $foreign = Prompt::factory()->priority(PromptPriority::Low)->create();

    $this->actingAs($user)
        ->patch(route('prompts.priority', $foreign), ['priority' => PromptPriority::High->value])
        ->assertNotFound();

    expect($foreign->refresh()->priority)->toBe(PromptPriority::Low);
});
