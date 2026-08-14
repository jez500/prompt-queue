# Remediation plan

Sequenced work to close the **High and Medium** findings in this audit.
Lows and product opportunities are parked in the backlog at the end.

**Delivery:** atomic commits on `feat/deployment-dev-env-and-ui-fixes`, one
finding (or one coherent pair) per commit.
**Gate before finishing:** `composer ci:check` (pint, phpstan, eslint, prettier,
vue-tsc, Pest), plus a browser pass at narrow (≤1099px), compact (1100–1259px)
and wide — there is no JS test harness, so that pass is not optional.
**Sizes:** S ≈ under an hour, M ≈ half a day, L ≈ a day or more.

Ordering is dependency-driven, not severity-driven: the backend contract changes
in Phase 1 are what let the Phase 2 autosave fixes be simple rather than clever.

---

## Phase 1 — Backend contracts and guards

Cheap, isolated, and everything later leans on it.

**1. Escape `LIKE` wildcards in prompt search** · S · [01](01-backend-and-database.md)
`Prompt::search` — replace the manual `str_replace` with `whereLike`/`orWhereLike`
so escaping is portable. Test: search `100%` against a prompt containing
`discount is 100% off` and one containing `100 percent`; assert only the literal
match returns. **Write the test first — it fails today.**

**2. Normalise email case** · S · [01](01-backend-and-database.md)
`ProfileValidationRules` gains `prepareForValidation` → lowercase plus the
`lowercase` rule; `pq:create-user` normalises instead of rejecting mixed case.
Tests: update profile to `Jez@Example.com`, assert stored lowercase and login
still succeeds; `pq:create-user --email=Mixed@Case.com` succeeds and stores
lowercase.

**3. Accept partial prompt updates, and a title at capture** · M · [01](01-backend-and-database.md), [03](03-tests.md)
Two changes that together unblock all of Phase 2:
- `PromptUpdateRequest` — `title`/`body`/`tags` become `sometimes`, so a caller
  can PATCH one field without echoing the rest.
- `PromptStoreRequest` — accept an optional `title`, and `PromptController::store`
  persists it.

Tests: PATCH with only `body` leaves status, priority and tags untouched (this is
the contract the request's own docblock already promises but nothing pins); PATCH
with only `tags` leaves the body untouched; POST with a title stores it.

**4. Pin the enum ↔ TypeScript contract** · S · [05](05-cross-cutting.md)
One Pest test asserting every `PromptStatus`, `PromptPriority` and `ProjectColor`
case appears in `types/prompts.ts` and in each corresponding class map key set.
Ten lines that close a whole class of silent drift, in the house style of
`ShellConsistencyTest`.

---

## Phase 2 — The High bugs

All three are in the autosave/copy path. Phase 1 makes them subtractive.

**5. Send only the fields that changed** · M · [02](02-frontend.md)
`usePromptAutosave` stops echoing `status`/`priority`/`project`/`tags` on body
saves and stops echoing `title`/`body` on tag saves. This alone kills both stale-echo
races (change status then type within 500ms → status reverts; type then add a tag →
body reverts).

**6. Stop reporting success on failed saves** · M · **High** · [02](02-frontend.md)
Move `savedAt` into `onSuccess`; add `onError` that restores `lastSaved` to the
pre-request snapshot so the edit stays pending and retries, and raises a toast.
Same treatment for the `create()` error path.

> ⚠️ `tests/Feature/PromptAutosaveGuardTest.php` pins exact source strings
> (`switchedPrompt`, `hasPendingEdits`, `if (hasPendingEdits) {` ordering) that this
> commit moves. Per `.ai/rules/testing.md`, update it **in the same commit** and say
> why in the message. Keep the guard's intent — the caret-preservation logic it
> protects is still correct and still needed.

**7. Persist the title typed on a new prompt** · S · **High** · [02](02-frontend.md)
With commit 3 in place, the fix is deletion: send `title` in the initial POST and
remove the follow-up `PATCH {title}` (which was a guaranteed 422) along with the
`justCreatedSnapshot` two-step it exists to support.

**8. Make the clipboard fallback actually select the body** · S · **High** · [02](02-frontend.md)
Add `data-prompt-body` to the detail-pane textarea so `selectBody()` finds it, and
pin the attribute's existence with a source-level assertion — its silent
disappearance is exactly what broke this. Verify over plain HTTP (not just
localhost, which is a secure context) — that is the deployment where it matters.

**Browser verification for this phase:** type through a save on a throttled
connection; force a 422 (paste a >255-char title) and confirm the indicator shows
failure and the text survives; change status mid-typing; add a tag mid-typing;
create a new prompt with a title and reload.

---

## Phase 3 — Frontend consistency

Extract first, then style once — the reverse order means tokenizing the same
markup twice.

**9. Extract the shared status/priority pill dropdown** · M · [02](02-frontend.md)
One component owning trigger, radio menu and the PATCH, consumed by both
`PromptQueueCard` and `PromptDetailPane`. Export `PROMPT_STATUSES` /
`PROMPT_PRIORITIES` from `lib/` and delete the three hardcoded option arrays
(`PromptQueueCard`, `PromptDetailPane`, `FilterBar`).

**10. Drive the prompt components from theme tokens** · M · [02](02-frontend.md)
Tokenize `PromptQueueCard`, `FilterBar`, `PromptQueueSidebar`, the detail-pane body
colour and `useProjectScopeNav`'s neutral dot. Decide explicitly whether
`#13131A`/`#35354A`/`#22222A` become new tokens (selected surface, selected border)
or snap to existing ones — `#22222A` is already a near-miss of the `border` token,
which is drift that has happened, not drift that might. Then extend the
`ShellConsistencyTest` no-raw-hex assertion to `components/prompts/**` so it can't
recur. Leave the sanctioned semantic hexes in `lib/` alone.

**11. Implement ⌘K and ⌘C** · M · [02](02-frontend.md), [05](05-cross-cutting.md)
A `useKeyboardShortcuts` composable owning every binding: ⌘K focuses search, ⌘C
copies the selected prompt (when focus isn't in an editable), N guarded with
`!metaKey && !ctrlKey && !altKey` — today Cmd/Ctrl+N opens a draft and swallows the
browser's own shortcut. Render the UI hint chips from the binding table so a hint
cannot exist without its binding. This is the fix for the README's claims too.

**12. Confirm project deletion** · S · [02](02-frontend.md)
The Delete button sits beside Save in the edit sheet and fires immediately. Add a
confirm stating the outcome ("Prompts are kept and moved to No project") — the
existing dialog primitives, not `window.confirm`.

---

## Phase 4 — Payload

**13. Send list excerpts, load the selected body on demand** · L · [05](05-cross-cutting.md)
`PromptResource` gains a server-computed `excerpt`; the list stops carrying full
bodies (currently up to 64KB each, to render one line), and the selected prompt's
body arrives via a partial reload or `Inertia::optional`. Update the TS types with
it. While here, consider `Inertia::once()`/deferred for the shared `projects`/`tags`
props, which re-query on every Inertia response including every autosave.

Do this **after** Phase 2 — it touches the same files, and it is much easier to
reason about once autosave no longer round-trips the body it just sent.

---

## Phase 5 — Ops and docs

Independent of everything above; safe to interleave if you want a break from the
frontend.

**14. `QUEUE_CONNECTION: sync` in compose** · S · [04](04-deployment-ci-docs.md)
Match reality — one process, no worker — and comment what adding real queue or
schedule work would require. Today the first `ShouldQueue` job anyone writes would
sit in the table silently forever.

**15. Self-host the real fonts** · S · [04](04-deployment-ci-docs.md)
Point the Bunny plugin at Space Grotesk / IBM Plex Sans / IBM Plex Mono, drop the
two render-blocking Google `@import`s, and remove the unused Instrument Sans. Fixes
offline/LAN rendering and stops sending visitor IPs to Google.

**16. Dependabot and PR image builds** · S · [04](04-deployment-ci-docs.md)
Add composer, npm and docker ecosystems; add a build-only Docker job to PRs so a
broken Dockerfile is caught before merge rather than at publish. Resolve the
mutable `shivammathur/setup-php@…# v2` pin while there.

**17. `pq:backup` using `VACUUM INTO`** · S · [04](04-deployment-ci-docs.md)
Five lines, atomic, safe on a live database — and it makes the README's backup
one-liner honest instead of a torn-copy risk. Test: run it, assert the output file
opens and has the expected tables.

**18. Correct the README** · S · [04](04-deployment-ci-docs.md)
Password rules (production is min 12 + symbols + an HIBP check, not "8 with mixed
case and a number" — and note HIBP needs outbound HTTPS), the backup command
(→ `pq:backup`), and the shortcut claims (now true, after commit 11).

---

## Sequencing summary

| Phase | Commits | Rough size | Unblocks |
| --- | --- | --- | --- |
| 1 — Backend contracts | 4 | ~1 day | Phase 2 |
| 2 — High bugs | 4 | ~1 day | Phase 4 |
| 3 — Frontend consistency | 4 | ~1.5 days | — |
| 4 — Payload | 1 | ~1 day | — |
| 5 — Ops and docs | 5 | ~0.5 day | — |

Phases 1→2 are the ones with a hard dependency. Phase 5 can be pulled forward at
any point. If the work has to stop early, stopping after Phase 2 leaves the
codebase strictly better with no half-finished refactor.

---

## Parked backlog (not in this plan)

**Low cleanups** — dead code (`TagInput`, `ProjectSidebarNav`, `AlertError`,
light-mode class maps, `sidebarOpen` prop, the 2FA request class, the
email-verification ceremony, `Pest.php` scaffolding, the stale `beforeEach` in
`ProjectManagementTest`), authorization consolidated to one pattern, OPcache and
`php.ini-production` tuning, CI dependency caching and a concurrency group,
tag-name case folding, prompt delete moved off `window.confirm`, non-ticking
relative timestamps, reorder PATCH when nothing moved, `User` behind a resource.

**Product opportunities** — programmatic prompt access for agents (token API or
MCP; the biggest gap against the product's own pitch), `pq:export`, duplicate
prompt, "Clear done", deletion undo, first-run hint on the login page,
`last_copied_at`. These want a design pass before an implementation plan.
