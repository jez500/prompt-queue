<?php

/*
|--------------------------------------------------------------------------
| Enum contract — PHP ↔ TypeScript
|--------------------------------------------------------------------------
|
| Statuses, priorities and project colours are declared twice: once as a PHP
| backed enum and once as a TypeScript union with class maps keyed by it.
| Adding a case to the PHP enum compiles, passes every other test, and then
| fails silently in the browser — an unstyled pill, a missing filter, a
| Record lookup returning undefined.
|
| There is no client-side test harness (see .ai/rules/testing.md), so this
| pins the two sides together at source level.
|
*/

use App\Enums\ProjectColor;
use App\Enums\PromptPriority;
use App\Enums\PromptStatus;

function tsSource(string $relativePath): string
{
    $path = resource_path('js/'.$relativePath);

    expect($path)->toBeReadableFile();

    return (string) file_get_contents($path);
}

/**
 * The quoted members of an exported TypeScript string union.
 *
 * @return array<int, string>
 */
function tsUnionMembers(string $relativePath, string $typeName): array
{
    $source = tsSource($relativePath);

    preg_match('/export type '.preg_quote($typeName, '/').'\s*=([^;]+);/', $source, $matches);

    expect($matches)->not->toBeEmpty("The TypeScript type [{$typeName}] is missing.");

    preg_match_all("/'([^']+)'/", $matches[1], $members);

    return $members[1];
}

/**
 * The top-level keys of an exported object literal.
 *
 * @return array<int, string>
 */
function tsObjectKeys(string $relativePath, string $constName): array
{
    $source = tsSource($relativePath);

    $declaration = strpos($source, 'const '.$constName);

    expect($declaration)->not->toBeFalse("The map [{$constName}] is missing.");

    $opening = strpos($source, '= {', $declaration);
    $closing = strpos($source, "\n};", (int) $opening);

    expect($opening)->not->toBeFalse()->and($closing)->not->toBeFalse();

    $body = substr($source, (int) $opening, (int) $closing - (int) $opening);

    preg_match_all('/^\s+([A-Za-z_]\w*):/m', $body, $keys);

    return $keys[1];
}

/**
 * The quoted entries of an exported array literal.
 *
 * @return array<int, string>
 */
function tsArrayEntries(string $relativePath, string $constName): array
{
    $source = tsSource($relativePath);

    $declaration = strpos($source, 'const '.$constName);

    expect($declaration)->not->toBeFalse("The array [{$constName}] is missing.");

    $opening = strpos($source, '= [', $declaration);
    $closing = strpos($source, '];', (int) $opening);

    $body = substr($source, (int) $opening, (int) $closing - (int) $opening);

    preg_match_all("/'([^']+)'/", $body, $entries);

    return $entries[1];
}

it('declares the same prompt statuses on both sides', function (): void {
    expect(tsUnionMembers('types/prompts.ts', 'PromptStatus'))
        ->toEqualCanonicalizing(array_column(PromptStatus::cases(), 'value'));
});

it('declares the same prompt priorities on both sides', function (): void {
    expect(tsUnionMembers('types/prompts.ts', 'PromptPriority'))
        ->toEqualCanonicalizing(array_column(PromptPriority::cases(), 'value'));
});

it('declares the same project colours on both sides', function (): void {
    expect(tsUnionMembers('types/prompts.ts', 'ProjectColor'))
        ->toEqualCanonicalizing(array_column(ProjectColor::cases(), 'value'));
});

it('keys every status class map by every status', function (string $map): void {
    expect(tsObjectKeys('lib/promptStatus.ts', $map))
        ->toEqualCanonicalizing(array_column(PromptStatus::cases(), 'value'));
})->with([
    'PROMPT_STATUS_LABELS',
    'PROMPT_STATUS_QUEUE_PILL_CLASSES',
    'PROMPT_STATUS_QUEUE_DOT_CLASSES',
]);

it('keys every priority class map by every priority', function (string $map): void {
    expect(tsObjectKeys('lib/promptPriority.ts', $map))
        ->toEqualCanonicalizing(array_column(PromptPriority::cases(), 'value'));
})->with([
    'PROMPT_PRIORITY_LABELS',
    'PROMPT_PRIORITY_QUEUE_PILL_CLASSES',
]);

it('keys every project colour class map by every colour', function (string $map): void {
    expect(tsObjectKeys('lib/projectColors.ts', $map))
        ->toEqualCanonicalizing(array_column(ProjectColor::cases(), 'value'));
})->with([
    'PROJECT_DOT_CLASSES',
    'PROJECT_BORDER_CLASSES',
    'PROJECT_TEXT_CLASSES',
]);

it('offers every project colour in the picker', function (): void {
    expect(tsArrayEntries('lib/projectColors.ts', 'PROJECT_COLORS'))
        ->toEqualCanonicalizing(array_column(ProjectColor::cases(), 'value'));
});
