# HANDOFF — Prompt Queue, subagent-driven execution

Written mid-execution because the session hit a usage limit. Another model should
resume from here.

## What this is

Building "Prompt Queue" (a low-friction holding pen for AI prompts) in a fresh
Laravel 13 + Inertia v3 + Vue 3 + Tailwind v4 app.

- **Spec:** `docs/superpowers/specs/2026-08-12-prompt-queue-design.md`
- **Plan:** `docs/superpowers/plans/2026-08-12-prompt-queue.md` (17 tasks, 0–16)
- **Ledger (authoritative progress record):** `.superpowers/sdd/2026-08-12-prompt-queue/progress.md`
- **Working dir:** `/shares/ubuntu/home/jez/sites/prompt-queue`
- **Branch:** `feature/prompt-queue` (branched from `f9d8f74`, the initial commit)

The process being followed is the `superpowers:subagent-driven-development` skill:
one fresh implementer subagent per task, then a task reviewer, then a fix loop if
the review finds anything, then a ledger entry. **The user chose `sonnet` for all
implementer and reviewer subagents** — keep using it.

## EXACT NEXT ACTION

**Task 13 was implemented but NOT yet reviewed.** That is the immediate next step.

```bash
cd /shares/ubuntu/home/jez/sites/prompt-queue
SDD=/home/jez/.claude/plugins/cache/claude-plugins-official/superpowers/6.2.0/skills/subagent-driven-development/scripts
"$SDD/review-package" docs/superpowers/plans/2026-08-12-prompt-queue.md 236fccf ac68a45
```

Then dispatch a task reviewer (sonnet) with three paths: the brief
(`task-13-brief.md`), the report (`task-13-report.md`), and the printed diff path.

Task 13's implementer reported: types:check clean, lint:check clean, `npm run build`
succeeded, IndexTest 10/10, full suite 86 passing / 4 pre-existing skips. It also
removed the `beforeEach` in `tests/Feature/Prompts/IndexTest.php` (a deferred item
from Task 7) and confirmed the tests still pass without it.

**Things worth telling the Task 13 reviewer to check specifically:**
- Copy is clipboard-FIRST: the status PATCH must fire only after a successful
  clipboard write. If the clipboard is unavailable or throws, it must fall back to
  selecting the body text + error toast and leave status untouched.
- Cmd/Ctrl+Enter submits; plain Enter must insert a newline. Empty/whitespace-only
  bodies must not submit.
- Filter changes must strip empty values from the query string. An empty `status[]`
  left in the URL is read server-side as an explicit filter and silently disables
  drag reordering.
- `PromptList` must NOT import any drag library yet (Task 15 adds it) but must
  accept `canReorder` and render handles conditionally.
- No `PromptEditSheet` (Task 14 owns it). The page's `editing` ref having no opener
  yet is expected, not a defect.

## Remaining work

| Task | State |
|---|---|
| 0–12 | Complete, reviewed clean, in ledger |
| 13 | **Implemented (`ac68a45`), review OUTSTANDING** |
| 14 | Not started — edit slide-over (`PromptEditSheet.vue`, `TagInput.vue`) |
| 15 | Not started — drag reordering; installs `sortablejs` + `vuedraggable@next` (the ONLY approved new dependency) |
| 16 | Not started — `ProjectFormDialog.vue`, sidebar trigger, full verification sweep |
| Final | Whole-branch review, then `superpowers:finishing-a-development-branch` |

Final review range when you get there: `f9d8f74..HEAD`. Point the final reviewer at
the deferred-minor and pattern lines in `progress.md` so it can triage them.

## Per-task loop (what I've been doing)

1. `"$SDD/task-brief" docs/superpowers/plans/2026-08-12-prompt-queue.md N` → prints a brief path.
2. Record `BASE=$(git rev-parse HEAD)` BEFORE dispatching.
3. Dispatch implementer (sonnet, `run_in_background: false`) with: one line of project
   context, the brief path introduced as "read this first, it is your requirements",
   interfaces from earlier tasks the brief can't know, the global constraints, and a
   report-file path (`task-N-report.md`). Never paste the whole plan or prior-task history.
4. `"$SDD/review-package" PLAN BASE HEAD` → dispatch reviewer (sonnet) with brief +
   report + diff paths. Reviewer must return BOTH a spec verdict and a quality verdict.
5. Minor findings → ledger as deferred, no fix loop. Critical/Important → fix loop:
   resume the original implementer via `SendMessage` to its agentId, then a scoped
   re-review over `FIX_BASE..HEAD`.
6. Append to ledger, mark todo complete, move on. Do not pause to check in with the user.

Do NOT fix findings yourself in the controller session.

## Traps already discovered — do not rediscover these

- **`php artisan wayfinder:generate` MUST be run with `--with-form`.** `vite.config.ts`
  sets `wayfinder({ formVariants: true })`; without the flag, six unrelated pre-existing
  files fail `types:check`. The plan has been amended.
- **API resources passed to Inertia need `->resolve()` at the call site**, or the prop
  serialises wrapped in a `data` key. Used in `PromptController@index` and
  `HandleInertiaRequests::share()`. A global `JsonResource::withoutWrapping()` was tried
  and **rejected in review** — do not reintroduce it.
- **Route ordering:** `prompts/reorder` must stay above `prompts/{prompt}`. Verified;
  don't let a later task reshuffle it.
- **Tailwind v4 cannot generate interpolated classes.** Colour classes live as complete
  literals in `resources/js/lib/projectColors.ts`.
- **`inBucket(null)` compiles to `whereNull('project_id')`**, never `= NULL`. Always use
  the scope, never a hand-written where.
- **`php artisan make:enum` scaffolds to the wrong namespace** in this install; the enums
  were hand-written into `app/Enums`.
- **The base `Controller` has no `AuthorizesRequests` trait** — use `Gate::authorize()`
  via the facade, not `$this->authorize()`.
- Policies return `Response::denyAsNotFound()` so strangers get 404, not 403. Tests
  assert `assertNotFound()`.

## Deferred minors (not blocking; final review should triage)

Full list with detail is in `progress.md`. Summary:
- `EnumsTest` doesn't assert `ProjectColor`'s backed values.
- `TagModelTest` doesn't prove the unique index is per-user rather than global (the
  equivalent Project test does).
- Commit `782b56a`'s message mentions a pivot it didn't create.
- `PromptModelTest` has no single test covering "split on newline THEN truncate".
- `PromptPolicyTest` asserts the 404 status only for `update`, not `delete`.
- No test covers renaming a project to its own unchanged name.
- `ProjectController@destroy` moves prompts with N individual UPDATEs.

## One accepted behavioural deviation

The plan's `Str::limit($firstLine, 80)` appends "..." giving 83 chars, contradicting the
plan's own `<= 80` assertion. The implementer passed an empty suffix, so derived titles
now truncate at exactly 80 characters **with no ellipsis**. Reviewer judged this the more
spec-faithful fix. If it reads badly in the UI, the alternative is `Str::limit($s, 77) . '…'`.

## Verification commands

```bash
php artisan test --compact                 # 86 passing, 4 pre-existing skips
npm run types:check && npm run lint:check && npm run build
vendor/bin/pint --dirty --format agent     # after any PHP change
```

Note: `pest-plugin-browser` is deliberately NOT installed — the user declined it, so
there are no browser/E2E tests in v1.
