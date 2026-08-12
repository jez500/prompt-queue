<?php

use App\Enums\PromptPriority;
use App\Enums\PromptStatus;

test('open statuses are todo and implementing', function () {
    expect(PromptStatus::open())->toBe([PromptStatus::Todo, PromptStatus::Implementing]);
});

test('statuses are backed by lowercase strings', function () {
    expect(PromptStatus::Todo->value)->toBe('todo')
        ->and(PromptStatus::Implementing->value)->toBe('implementing')
        ->and(PromptStatus::Done->value)->toBe('done');
});

test('priorities are backed by lowercase strings', function () {
    expect(PromptPriority::Low->value)->toBe('low')
        ->and(PromptPriority::Normal->value)->toBe('normal')
        ->and(PromptPriority::High->value)->toBe('high');
});
