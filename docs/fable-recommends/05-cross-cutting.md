# Cross-cutting audit — data flow, contracts, experience

Scope: the seams between backend and frontend — payload shape, type contracts,
end-to-end behaviour — plus product-level opportunities measured against the
project's own stated goals (README, DESIGN.md "keyboard-first").

## Data flow & scale

### [Medium] Every prompt's full body ships to the list, which renders one line of it
`app/Http/Resources/PromptResource.php:28`, `PromptQueueCard.vue` (preview),
`PromptController::index` (no pagination)

The index sends **all** matching prompts with their complete bodies (up to 64KB
each). The list card displays `body.split('\n')[0]` — one line. Only the *selected*
prompt's body is ever fully read. At a few hundred prompts this is fine; at 2,000
prompts with real agent-prompt-sized bodies the initial payload is tens of
megabytes, re-serialized on **every** autosave/status/reorder round-trip
(`only: ['prompts']` still rebuilds the whole collection server-side), and the list
renders every card with no windowing.

**Recommendation (staged, only as needed):** first, send a server-computed
`excerpt` in the list payload and deliver the full body only for the selected
prompt (Inertia v3 partial reload with a `prompt` prop, or `Inertia::optional`).
That alone cuts the steady-state payload by ~95% for long prompts. Pagination/
virtual scrolling can wait until the list itself is slow.

### [Low] Autosave round-trips rebuild props the client already has
Each debounced PATCH → `back()` → full `prompts` re-query with `project`/`tags`
eager-loaded, plus the shared-prop projects/tags queries (see backend audit). The
client only needs to know "saved, here's the new `updatedAt`". Inertia v3 supports
responding to the PATCH without reloading `prompts` at all (`only: []` plus
optimistic state), which would make typing latency independent of queue size.

### [Low] Two tabs editing the same prompt silently last-write-wins
Autosave sends no version/`updatedAt` check, so tab B's 500ms-old snapshot
overwrites tab A's save with no signal. Acceptable for a single-user tool — but the
fix is cheap if it ever bites: send `updatedAt` with the PATCH and 409 when stale.

## Contract drift (backend ↔ frontend)

### [Medium] Enums and resource shapes are hand-mirrored with nothing pinning them
- `App\Enums\PromptStatus` ↔ `types/prompts.ts` `'todo' | 'implementing' | 'done'`
- `App\Enums\PromptPriority` ↔ `'low' | 'normal' | 'high'`
- `App\Enums\ProjectColor` ↔ the TS union **and** the class maps in
  `lib/projectColors.ts`
- `PromptResource` / `ProjectResource` ↔ `Prompt` / `Project` types

Verified in sync today (field by field). But adding a PHP enum case compiles, passes
every PHP test, and silently breaks the frontend (unstyled pill, missing filter,
`Record<...>` lookup returning `undefined`). The project already has a house style
for exactly this: a source-level pin à la `ShellConsistencyTest`.
**Recommendation:** one Pest test that asserts `PromptStatus::cases()` values all
appear in `types/prompts.ts` and in each `PROMPT_STATUS_*` map key set (same for
priority and color). Ten lines, closes the whole class of drift.

### [Low] The status/priority option lists are declared in three components
`PromptQueueCard.vue`, `PromptDetailPane.vue`, `FilterBar.vue` each hardcode
`const statuses: PromptStatus[] = ['todo', 'implementing', 'done']` (and
priorities). Export `PROMPT_STATUSES` / `PROMPT_PRIORITIES` from the existing
`lib/promptStatus.ts` / `promptPriority.ts` — one ordering decision, one place.
(Folding this into the shared pill-dropdown component from `02-frontend.md`
removes the duplication entirely.)

## Experience, end to end

### [Medium] "Keyboard-first" is a stated principle with one binding
DESIGN.md: *"Dense, quiet, keyboard-first."* README: *"Keyboard-first — N for a new
prompt, ⌘K to search."* Implemented: `N`. Not implemented: ⌘K (hinted in the UI),
⌘C (hinted in the UI), any list navigation (j/k or arrows to move selection,
Enter to open, Esc to return at narrow). For a queue you triage daily, arrow-key
selection is arguably worth more than either advertised chord. Recommendation: a
small `useKeyboardShortcuts` composable owning all bindings (with the
modifier-guard fix from `02-frontend.md`), and make the UI hints render from it so
a hint can't exist without its binding.

### [Low] Done prompts accumulate forever
The default view hides Done, so the queue *looks* clean while the table and the
"All prompts" payload grow without bound. There's no bulk action, archive, or
purge. A "Clear done" affordance (bulk delete or archive flag) in the Done filter
view would keep long-lived instances tidy — and matters more once payload size
does (above).

### [Low] No undo for prompt deletion
Delete confirms (a native `confirm`, see frontend audit) but is irreversible. The
toast infrastructure already exists — an "Undo" action on the deletion toast
(soft-delete + restore, or recreate from the flashed payload) is the standard
resolution and softens the confirm dialog too.

### [Low] First-run dead end between container start and first login
A fresh instance greets the operator with a login form and no path forward unless
they've kept the README open (`pq:create-user`). The login page could show a
one-line hint when `User::count() === 0` ("No users yet — create one with php
artisan pq:create-user"). Zero information leak (an empty instance is not a
secret), removes the only rough edge in an otherwise smooth install.

## Opportunities (on-thesis, not defects)

- **Programmatic prompt access.** The product's entire premise is handing prompts
  to agents, and the only exit today is the system clipboard. A read-only token
  API (`GET /api/prompts/{id}` returning the body, plus "next in queue") — or an
  MCP server exposing the queue — would let agents pull work directly. This is the
  single highest-leverage feature gap relative to the README's own pitch.
- **Export.** `pq:export` (JSON/Markdown of all prompts) pairs with the backup
  story in `04-deployment-ci-docs.md` and de-risks the single-SQLite-file design.
- **Duplicate prompt.** Prompts get reused as templates; today reuse means
  copy-paste into a new draft. One action on the detail header.
- **Copy metadata.** The copy→Implementing advancement already exists; recording
  `last_copied_at` would let the queue show what's actually been handed off, which
  is the state the status pills approximate by hand.

## What's done well (cross-cutting)
- The bucket/position model is coherent across all layers: one definition of a
  bucket (`inBucket`), server-computed `canReorder`, drag offered only when the
  order is unambiguous, and the exact-id-set reorder validation — client and server
  can't disagree about what an order means.
- Copy-advances-status is a genuinely good product mechanic, implemented once and
  reused by both the button and (intended) shortcut path.
- Filters are canonical in the URL; back/forward and sharing work; server defaults
  (open statuses) and client pills agree.
- Toast/flash plumbing (`Inertia::flash` → `initializeFlashToast`) is one small,
  consistent channel rather than per-page ad-hoc notices.
