---
paths:
  - resources/js/components/prompts/PromptQueueSidebar.vue
---

# Prompts

## Only project rows go inside the sidebar draggable
The sidebar scope list is three blocks, not one loop: "All prompts", a
`<draggable>` over `projectItems`, then the Inbox row. The two fixed ends must
stay outside it — inside, they can be dragged into the middle and their ids get
posted to `projects.reorder`, which only knows about projects.

`useProjectScopeNav` still returns the flat `items` for the narrow topbar; the
sidebar uses `allPromptsItem` / `projectItems` / `inboxItem` instead.

The hold is 800ms in two places that must move together: sortable's `delay` in
the sidebar, and the press-cue transition in `ProjectScopeRow.vue`. Sortable
has no "press started" event, so the cue is the row's own pointerdown timer.

A drop ends with a click on the row it landed on, which would navigate away —
`suppressNavigation` swallows it for 250ms. Bound to a timeout, not the
request, so a slow reorder cannot leave the rows unclickable.

`tests/Feature/Projects/SidebarReorderChromeTest.php` pins all of this.
