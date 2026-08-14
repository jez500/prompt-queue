# Composables

**Globs:** `resources/js/composables/**`

## Partial reloads must list every prop the change is shown in

`only: [...]` on an Inertia visit drops shared props too. A tag save that
reloaded only `prompts` left the tag row (which comes from `selected`) and the
filter bar (the shared `tags` prop) showing the old data, so adding or removing
a tag looked like it did nothing at all.

- Tag changes: `only: ['prompts', 'selected', 'tags']`.
- A project move adds `projects` and `canReorder` — the counts and the
  drag-to-reorder flag both move with it.
- Body and title autosave deliberately stays on `only: ['prompts']`. Pulling
  `selected` back mid-typing is exactly what the stale-echo guard in
  `usePromptAutosave` exists to survive; do not widen it without reading that
  guard and `tests/Feature/PromptAutosaveGuardTest.php` first.
