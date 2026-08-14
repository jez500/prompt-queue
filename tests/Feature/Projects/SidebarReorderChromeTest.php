<?php

/*
|--------------------------------------------------------------------------
| Sidebar reorder — chrome invariants
|--------------------------------------------------------------------------
|
| There is no client-side test harness (see .ai/rules/testing.md), so the parts
| of the long-press reorder that silently stop working are pinned at source
| level: the partial-reload prop list, the hold delay, and the two guards that
| keep a drag from being read as a click.
|
*/

function sidebarSource(string $path): string
{
    $full = resource_path($path);

    expect($full)->toBeReadableFile();

    return (string) file_get_contents($full);
}

it('brings back everything a reorder is shown in', function (): void {
    expect(sidebarSource('js/components/prompts/PromptQueueSidebar.vue'))
        ->toContain("only: ['projects'],");
});

it('holds for the full delay before a drag takes over', function (): void {
    /*
      Shorter and a scroll starts dragging rows; longer and the press reads as
      broken. The row's press cue transitions over the same 800ms, so the two
      have to move together.
    */
    expect(sidebarSource('js/components/prompts/PromptQueueSidebar.vue'))
        ->toContain('const REORDER_DELAY = 800;')
        ->toContain(':delay="REORDER_DELAY"');

    expect(sidebarSource('js/components/prompts/ProjectScopeRow.vue'))
        ->toContain('duration-[800ms]');
});

it('does not navigate on the click that ends a drag', function (): void {
    expect(sidebarSource('js/components/prompts/PromptQueueSidebar.vue'))
        ->toContain(':suppress-navigation="justDragged"');

    expect(sidebarSource('js/components/prompts/ProjectScopeRow.vue'))
        ->toContain('@click.capture="onClick"')
        ->toContain('event.preventDefault();');
});

it('only lets the project rows reorder', function (): void {
    /*
      "All prompts" and the Inbox are fixed ends of the list. Rendering them
      inside the draggable would let the user drag them into the middle and
      then send their ids to a reorder endpoint that only knows projects.
    */
    $sidebar = sidebarSource('js/components/prompts/PromptQueueSidebar.vue');

    expect($sidebar)
        ->toContain('v-model="ordered"')
        ->toContain(':item="allPromptsItem"')
        ->toContain(':item="inboxItem"');

    $draggable = (string) preg_replace('/^.*<draggable/su', '', $sidebar);
    $draggable = (string) preg_replace('#</draggable>.*$#su', '', $draggable);

    expect($draggable)
        ->not->toContain('allPromptsItem')
        ->not->toContain('inboxItem');
});
