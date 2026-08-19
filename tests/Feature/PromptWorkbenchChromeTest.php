<?php

/*
|--------------------------------------------------------------------------
| Prompt workbench — chrome invariants
|--------------------------------------------------------------------------
|
| There is no client-side test harness (see .ai/rules/testing.md), so the
| structural parts of this screen are pinned at source level. Each case here
| stands for a bug that was reported from the running app.
|
*/

function workbenchSource(string $path): string
{
    $full = resource_path($path);

    expect($full)->toBeReadableFile();

    return (string) file_get_contents($full);
}

it('brings back everything a tag change is shown in', function (): void {
    /*
      A tag save that only reloaded `prompts` left the tag row and the filter
      bar showing the old list, so adding or removing a tag looked like it did
      nothing at all.
    */
    expect(workbenchSource('js/composables/usePromptAutosave.ts'))
        ->toContain("only: ['prompts', 'selected', 'tags'],");
});

it('lets a draft carry tags before it has been saved', function (): void {
    /*
      The tag row was gated on an existing prompt, so tags could only be added
      after the first save. They are held in the composable now and ride along
      with the create.
    */
    expect(workbenchSource('js/components/prompts/PromptDetailPane.vue'))
        ->toContain('v-for="tag in autosave.tags.value"')
        ->not->toContain('v-for="tag in prompt.tags"');

    expect(workbenchSource('js/composables/usePromptAutosave.ts'))
        ->toContain('tags: tags.value,');
});

it('offers the project as a menu that moves the prompt', function (): void {
    expect(workbenchSource('js/components/prompts/PromptDetailPane.vue'))
        ->toContain('<PromptProjectPill')
        ->toContain('@update:model-value="handleProjectChange"')
        ->toContain('autosave.setProject(projectId,');

    expect(workbenchSource('js/composables/usePromptAutosave.ts'))
        ->toContain('const setProject = (');
});

it('follows a prompt into the project it was moved to', function (): void {
    /*
      Moving a prompt out of the project being viewed dropped it off the list
      and emptied the editor it was moved from. The id travels with the event
      because by the time the move lands, `selected` has moved on to whatever
      is left in the bucket.
    */
    expect(workbenchSource('js/composables/usePromptFilters.ts'))
        ->toContain('const followPrompt = (projectId: number | null, promptId: number): void => {');

    expect(workbenchSource('js/pages/prompts/Index.vue'))
        ->toContain('followPrompt(projectId, promptId);')
        ->toContain('if (filters.value.project === null) {');

    expect(workbenchSource('js/components/prompts/PromptDetailPane.vue'))
        ->toContain("emit('moved', projectId, movedId)");
});

it('marks the chosen status once', function (): void {
    /*
      The menu's own checked dot sat next to the status colour dot, so the
      current row showed two.
    */
    expect(workbenchSource('js/components/prompts/PromptStatusPill.vue'))
        ->toContain('<template #indicator-icon><span /></template>');
});

it('sends the logo home', function (): void {
    expect(workbenchSource('js/components/prompts/PromptQueueSidebar.vue'))
        ->toContain(':href="index()"');
});

it('offers a way out of an empty list', function (): void {
    expect(workbenchSource('js/components/prompts/PromptListPane.vue'))
        ->toContain('Add a prompt')
        ->toContain('@click="emit(\'newPrompt\')"');
});

it('does not label the detail pane', function (): void {
    expect(workbenchSource('js/components/prompts/PromptDetailPane.vue'))
        ->not->toContain('eyebrow="Prompt"');
});

it('styles scrollbars from the border ramp rather than raw colour', function (): void {
    $css = workbenchSource('css/app.css');

    expect($css)
        ->toContain('scrollbar-width: thin;')
        ->toContain('::-webkit-scrollbar-thumb {')
        ->toContain('background-color: var(--border-strong);');
});

it('leaves the card summary empty rather than repeating the title', function (): void {
    expect(workbenchSource('js/components/prompts/PromptQueueCard.vue'))
        ->toContain('v-if="preview"')
        ->not->toContain('Empty — open to write it');
});
it('names the title field for what belongs in it', function (): void {
    /*
      "Untitled prompt" described the prompt's state rather than asking for
      anything, so the field read as a label and people typed past it.
    */
    expect(workbenchSource('js/components/prompts/PromptDetailPane.vue'))
        ->toContain('placeholder="One liner…"')
        ->not->toContain('placeholder="Untitled prompt"');
});

it('rotates the body placeholder rather than fixing one line', function (): void {
    expect(workbenchSource('js/components/prompts/PromptDetailPane.vue'))
        ->toContain(':placeholder="bodyPlaceholder"')
        ->toContain('bodyPlaceholder.value = nextBodyPlaceholder();');
});

it('advances the placeholder rotation once per draft', function (): void {
    /*
      The detail pane unmounts every time the narrow layout returns to the
      list. Advancing the cursor when the component mounts as well as when a
      draft opens stepped it twice, and an even-length list stepped by two
      never shows half its entries.
    */
    expect(workbenchSource('js/components/prompts/PromptDetailPane.vue'))
        ->toContain('ref(currentBodyPlaceholder())')
        ->not->toContain('ref(nextBodyPlaceholder())');
});

it('offers enough placeholders for the rotation to be worth having', function (): void {
    $source = workbenchSource('js/lib/promptPlaceholders.ts');

    preg_match('/const BODY_PLACEHOLDERS = \[(.*?)\];/s', $source, $matches);

    expect($matches)->toHaveCount(2, 'The placeholder list is gone.');

    preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $matches[1], $entries);

    $lines = $entries[1];

    expect(count($lines))->toBeGreaterThanOrEqual(8)
        ->and($lines)->toBe(array_values(array_unique($lines)));
});
