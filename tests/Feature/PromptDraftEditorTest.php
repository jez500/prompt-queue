<?php

/*
|--------------------------------------------------------------------------
| Prompt workbench — what the editor is handed
|--------------------------------------------------------------------------
|
| Only the prompt named in ?prompt= carries a body, so the editor's prompt is
| a server-resolved prop. Two moments have no such prompt and must not fall
| back to the previous selection:
|
|   - A draft. Passing the old selection left "New prompt" showing the prompt
|     that was already open, with no way to reach an empty form.
|   - The gap after a create, before the new prompt comes back as `selected`.
|     Passing the old selection there would replace what was just typed.
|
| There is no client-side test harness (see .ai/rules/testing.md), so this
| pins the wiring at source level. The behavioural verification is a browser
| repro: open a prompt, press New, and confirm both fields are empty.
|
*/

function promptsIndexPageSource(): string
{
    $path = resource_path('js/pages/prompts/Index.vue');

    expect($path)->toBeReadableFile();

    return (string) file_get_contents($path);
}

it('hands the editor a computed prompt rather than the raw selection', function (): void {
    expect(promptsIndexPageSource())
        ->toContain(':prompt="editedPrompt"')
        ->not->toContain(':prompt="props.selected"');
});

it('gives the editor nothing to edit while a draft is open', function (): void {
    $source = promptsIndexPageSource();

    $computed = strpos($source, 'const editedPrompt');
    $guard = strpos($source, 'if (drafting.value) {', (int) $computed);
    $fallback = strpos($source, 'return props.selected;', (int) $computed);

    expect($computed)->not->toBeFalse('The editor prop is no longer computed.')
        ->and($guard)->not->toBeFalse('The draft guard is gone — New would reopen the old prompt.')
        ->and($fallback)->not->toBeFalse()
        ->and($guard)->toBeLessThan($fallback, 'The draft must be answered before the selection is.');
});

it('waits for the created prompt instead of showing the previous one', function (): void {
    expect(promptsIndexPageSource())
        ->toContain('const pendingCreatedId = ref<number | null>(null);')
        ->toContain('props.selected?.id !== pendingCreatedId.value');
});
