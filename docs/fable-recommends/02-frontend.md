# Frontend audit

Scope: `resources/js/` (pages, components, composables, lib, types, app.ts). Every
non-generated, non-`ui/` file read in full. Settled decisions from `.ai/rules`
(single shell, dark-only, mono metadata, sanctioned semantic hexes in `lib/`) are
not flagged.

## Bugs

### [High] A failed autosave reports "Saved" and can silently lose the user's text
`resources/js/composables/usePromptAutosave.ts:112-133`

`patch()` sets `lastSaved` to the new snapshot *before* the request, and the only
completion hook is `onFinish` — which fires on **error too** and unconditionally sets
`savedAt`, turning the indicator green. There is no `onError` handler and nothing in
`PromptDetailPane` renders `page.props.errors`. Failure scenario: type a title longer
than 255 chars (server 422s), or the session expires, or the network drops — the UI
says "Saved", and because `lastSaved` already equals the typed text,
`hasPendingEdits` is false, so the next prompts reload **replaces the textarea with
the stale server copy**. The user's edit is gone with a green tick next to it.

**Recommendation:** move `savedAt = Date.now()` into `onSuccess`; add `onError` that
restores `lastSaved` to the pre-request snapshot (so the edit stays "pending" and
retries on the next keystroke) and surfaces a toast ("Couldn't save — retrying").
The same applies to the `create()` error path, which currently just resets flags.

### [High] A title typed before a new prompt first saves is never persisted
`resources/js/composables/usePromptAutosave.ts:184-197`

After the initial POST (which has no `title` field), the composable follows up with
`router.patch(..., { title: pendingTitle })`. But `PromptUpdateRequest` requires
`body` (`app/Http/Requests/PromptUpdateRequest.php:26`), so that PATCH is a
guaranteed 422 — the title silently never reaches the server, while `onFinish` sets
"Saved". Reproduce: press N, type a title, then a body, wait for autosave, reload —
the title is gone. **Recommendation:** include `body` (and status/priority/project)
in the follow-up PATCH — or add `title` to `PromptStoreRequest` and send it in the
initial POST, deleting the two-step dance entirely.

### [High] The clipboard fallback selects nothing — every copy fails on plain-HTTP deploys
`resources/js/composables/useCopyPrompt.ts:10-26,43-56`

When `navigator.clipboard` is unavailable, `selectBody()` looks for
`[data-prompt-body="<id>"]` — **no element in the codebase has that attribute**, so
nothing is selected while the toast claims "the text is selected, copy it manually."
This is not an edge case for a self-hosted app: `window.isSecureContext` is false on
any LAN/Docker deployment served over plain HTTP, so the primary feature of the app
(copy a prompt to hand to an agent) is broken there. **Recommendation:** add the
attribute to the detail-pane textarea (or select/copy via a hidden textarea +
`document.execCommand('copy')` as a legacy path), and cover it with a source-level
assertion the way `ShellConsistencyTest` pins other invariants.

### [Medium] Autosave PATCHes echo stale sibling fields and can revert concurrent changes
`resources/js/composables/usePromptAutosave.ts:114-125,258-283`

`patch()` always sends `status: target.status, ... project, tags` taken from the
last **server** props, and `updateTags()` symmetrically sends the server's
`body`/`title`. Two interleavings go wrong: (1) change status from the pill, then
type within ~500ms — the body PATCH carries the *old* status and reverts the change;
(2) type, then add a tag before the debounce fires — the tag PATCH carries the *old*
body, and since `lastSaved` already claims the new body is saved, the next reload
silently restores the old text. The backend already treats omitted `status`/
`priority` as "keep" — **send only the fields each action actually changes** (make
`title`/`body`/`tags` `sometimes` on `PromptUpdateRequest`), which fixes both races
and the High finding above.

### [Medium] Advertised keyboard shortcuts don't exist
`resources/js/components/prompts/FilterBar.vue:58` (⌘K), `PromptDetailPane.vue:270` (⌘C),
`resources/js/layouts/prompts/PromptQueueLayout.vue:37-50`

The search field renders a ⌘K hint and the copy button a ⌘C hint, but the only
binding in the app is plain `N` (new prompt). Neither ⌘K nor ⌘C is handled anywhere.
Also, the `N` handler ignores modifier keys, so Cmd/Ctrl+N (and any browser/OS
chord containing N) opens a draft and calls `preventDefault()`. **Recommendation:**
implement ⌘K → focus search and ⌘C → copy selected prompt (when focus isn't in an
editable), guard the N handler with `!event.metaKey && !event.ctrlKey && !event.altKey`,
or remove the hints.

## Design-system consistency

### [Medium] Raw hex chrome throughout the prompt components — including off-token values
- `PromptQueueCard.vue` — `#13131A`, `#35354A` (selected card, not tokens),
  `#1C1C24`/`#0D0D12`/`#15151C`/`#F2F2F4`/`#22222C`/`#8A8A96`/`#5A5A66`/`#4A4A55`/`#2E2E3A`
  (all **exact token values** written by hand), `#63636F`, `#9A9AA6` (near-misses of
  `subtle-foreground`/`secondary-foreground`)
- `FilterBar.vue` — `#22222A` borders (close to, but **not**, the `#1C1C24` border
  token — drift has already happened), `#6E56F8`/`#C6BBFF` (primary exists as a token)
- `PromptQueueSidebar.vue` — `bg-[#101016]`, `bg-[#16161C]`
- `PromptDetailPane.vue` — `text-[#C8C8D2]` on the body textarea (chrome, not semantic)
- `useProjectScopeNav.ts:9` — `bg-[#5A5A66]` for the neutral dot, where
  `pages/prompts/Index.vue:96` uses `bg-faint-foreground` for the *same* dot

This is exactly the drift `.ai/rules/design-tokens.md` warns about: the same control
styled from tokens in one file and hex in its sibling. **Recommendation:** tokenize
the exact matches mechanically; decide whether `#13131A`/`#35354A`/`#22222A` are new
tokens (selected-surface, selected-border) or should snap to existing ones; then
extend the `ShellConsistencyTest` no-raw-hex assertion to
`resources/js/components/prompts/**` so it can't regress. (The status/priority pill
hexes in `lib/promptStatus.ts`/`promptPriority.ts` are sanctioned — leave those.)

### [Medium] The status/priority pill dropdown is implemented twice, differently
`PromptQueueCard.vue:88-160` and `PromptDetailPane.vue` (status + priority blocks)

Same trigger pill, same radio dropdown, same PATCH — duplicated in both files, one
styled with tokens and one with raw hex, each maintaining its own `statuses`/
`priorities` arrays and patch calls. **Recommendation:** extract a shared
`PromptStatusPill.vue` / `PromptPriorityPill.vue` (or one generic pill-dropdown) that
owns the PATCH; the card and pane pass size/alignment. This also collapses the
duplicated `router.patch` logic in `PromptQueueCard` vs `usePromptAutosave`.

## UX

### [Medium] Project deletion has no confirmation
`resources/js/components/projects/ProjectEditSheet.vue:56-117`

The Delete button in the edit sheet fires `form.delete` immediately. Prompt deletion
confirms (`window.confirm`), account deletion confirms with a password — project
deletion, sitting 40px from Save, doesn't. The prompts survive (re-bucketed to
"No project"), but the grouping is gone with one mis-click. **Recommendation:** add a
confirm step stating the outcome ("Prompts will be kept and moved to No project").

### [Low] Prompt deletion uses `window.confirm` while everything else uses design-system dialogs
`PromptDetailPane.vue:handleDelete`. Swap for the existing `Dialog`/AlertDialog
pattern (`DeleteUser.vue` is the in-house reference) for visual and keyboard
consistency.

### [Low] Relative timestamps never tick
`resources/js/lib/relativeTime.ts` output is computed once per render; "just now"
stays until the next prop change. A `useNow`-driven recompute (vueuse) is enough.

### [Low] Reorder PATCHes even when the drag didn't move anything
`PromptListPane.vue:persist` — vuedraggable fires `@end` on every drop; compare
`ordered` ids with `prompts` ids before issuing the request.

## Dead code (delete all of these)

- `components/prompts/TagInput.vue` — never imported; the detail pane hand-rolls its
  own tag UI instead. Either adopt it there or delete it (two tag-input
  implementations is how they drift apart).
- `components/projects/ProjectSidebarNav.vue` — 95 lines, never imported.
- `components/AlertError.vue` — never imported.
- `lib/promptStatus.ts` `PROMPT_STATUS_BADGE_CLASSES` + `PROMPT_STATUS_FILTER_ACTIVE_CLASSES`,
  `lib/promptPriority.ts` `PROMPT_PRIORITY_BADGE_CLASSES` — light-mode starter-kit
  class maps, never imported (the app is dark-only).
- `types/auth.ts` `Passkey` type and the `@chisel-passkeys` markers — no passkey
  feature exists.
- The `sidebarOpen` shared prop: computed on **every request** in
  `HandleInertiaRequests.php:63` and typed in `global.d.ts`, but the shell reads
  localStorage (`pq.sidebar`) instead — the prop, the `sidebar_state` cookie read,
  and its encryption exemption in `bootstrap/app.php` are all vestigial.
- `types/auth.ts` `User.avatar` — backend never sends it.

## TypeScript quality

- [Low] `useCurrentUrl.ts` calls `usePage()` at module scope (fragile under SSR/HMR)
  and `whenCurrentUrl` uses bare `any` for both branches.
- [Low] `Prompt`/`Project` types are hand-mirrored from the PHP resources with
  nothing pinning them together — see the cross-cutting report for a contract test
  recommendation.
- [Trivial] `app.ts` progress bar color is `#4B5563` (Tailwind gray-600, off-palette)
  — use the primary `#6e56f8`.

## What's done well
- `usePromptAutosave`'s caret-preservation logic (don't adopt the server echo while
  edits are pending, `justCreatedId` handshake) is careful and well-commented — the
  gaps above are in the *error* paths, not the happy path.
- Partial reloads are used consistently (`only: ['prompts', ...]`) on every mutation.
- Filters live in the URL (shareable, back-button-friendly) with a single
  `usePromptFilters` owner, debounced search included.
- Shell primitives (`AppPane`, `PaneHeader`, `NarrowTopBar`) are genuinely reused;
  icon-only buttons carry both tooltip and `aria-label` per the rules.
- Reorder failure rolls the list back to the pre-drag snapshot.
- `useCopyPrompt` degrades thoughtfully in intent (secure-context check, advance-on-copy)
  even though the fallback DOM hook is missing.
- Empty states exist for the list pane and the no-selection detail pane.
