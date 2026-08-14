<?php

/*
|--------------------------------------------------------------------------
| Prompt autosave — derived titles
|--------------------------------------------------------------------------
|
| A prompt saved with an empty title takes one from the first line of its
| body, minus leading whitespace and Markdown heading marks. A single-line
| body derives nothing: the list already falls back to showing that line, and
| committing it would freeze the opening words of a prompt mid-sentence.
|
| There is no client-side test harness (see .ai/rules/testing.md), so this
| pins the rules at source level. The behavioural verification is a browser
| repro: type a heading, press Enter, type a second line, and watch the title
| field fill in after the debounce.
|
*/

function autosaveComposableSource(): string
{
    $path = resource_path('js/composables/usePromptAutosave.ts');

    expect($path)->toBeReadableFile();

    return (string) file_get_contents($path);
}

it('derives a title from the first line, stripped of whitespace and heading marks', function (): void {
    expect(autosaveComposableSource())
        ->toContain('export function deriveTitleFromBody(body: string): string | null {')
        ->toContain("body.indexOf('\\n')")
        ->toContain("replace(/^[\\s#]+/, '')");
});

it('derives nothing from a body that is still a single line', function (): void {
    expect(autosaveComposableSource())
        ->toContain("if (!body.trimEnd().includes('\\n')) {");
});

it('only ever fills a title that is empty', function (): void {
    $source = autosaveComposableSource();

    $guard = strpos($source, "if (title.value.trim() !== '') {");
    $assignment = strpos($source, 'title.value = derived;');

    expect($guard)->not->toBeFalse('The empty-title guard is gone.')
        ->and($assignment)->not->toBeFalse()
        ->and($guard)->toBeLessThan(
            $assignment,
            'The guard must return before the title is written, or a typed title gets overwritten.'
        );
});

it('derives on both save paths, so the first save carries a title too', function (): void {
    $source = autosaveComposableSource();

    /*
      The create path matters most: a follow-up patch carrying only a title is
      rejected by the update endpoint, so a title missed here is missed for
      good until the body is edited again.
    */
    expect(substr_count($source, 'applyDerivedTitle();'))->toBe(2);
});

it('caps a derived title well inside the column limit', function (): void {
    expect(autosaveComposableSource())
        ->toContain('const DERIVED_TITLE_LENGTH = 80;')
        ->toContain('firstLine.slice(0, DERIVED_TITLE_LENGTH)');
});
