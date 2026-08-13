<?php

/*
|--------------------------------------------------------------------------
| Prompt autosave — stale-echo guard
|--------------------------------------------------------------------------
|
| The detail pane used to reload title/body from the `prompts` prop on every
| refresh, including the echo of its own in-flight save. Anything typed while
| that request was outstanding got replaced by the server's older copy, which
| rewrites the textarea and throws the caret to the end — reproduced as a jump
| from offset 55 to 116 with 50 keystrokes lost.
|
| There is no client-side test harness (see .ai/rules/testing.md), so this
| pins the guard at source level. The behavioural verification is a browser
| repro: type, pause ~520ms for the debounce, then keep typing through the
| round trip on a throttled connection.
|
*/

function autosaveSource(): string
{
    $path = resource_path('js/composables/usePromptAutosave.ts');

    expect($path)->toBeReadableFile();

    return (string) file_get_contents($path);
}

it('only reloads the editor when the edited prompt actually changes', function (): void {
    expect(autosaveSource())
        ->toContain('const switchedPrompt = incomingId !== editedId.value;')
        ->toContain('if (!switchedPrompt) {')
        ->toContain('hasPendingEdits');
});

it('bails out of the refresh instead of overwriting pending edits', function (): void {
    $source = autosaveSource();

    $guard = strpos($source, 'if (hasPendingEdits) {');
    $assignment = strpos($source, 'body.value = value?.body');

    expect($guard)->not->toBeFalse('The pending-edits guard is gone.')
        ->and($assignment)->not->toBeFalse()
        ->and($guard)->toBeLessThan(
            $assignment,
            'The guard must return before title/body are reassigned, or typing gets clobbered again.'
        );
});

it('ships no debug instrumentation', function (): void {
    expect(autosaveSource())
        ->not->toContain('console.log')
        ->not->toContain('DEBUG-INSTRUMENTATION');
});
