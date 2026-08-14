# Test suite audit

Scope: all of `tests/`, `phpunit.xml`, `Pest.php`, cross-referenced against every
route, controller, policy and command. Suite run: **116 tests, 112 passed,
4 skipped (Fortify features off, correctly guarded), 459 assertions, 2.8s.**
The no-JS-harness decision from `.ai/rules/testing.md` is respected — findings below
work within it.

## Coverage gaps

### [Medium] The partial-update contract is documented but not pinned
`app/Http/Requests/PromptUpdateRequest.php:52-55` promises "a partial save from the
slide-over cannot silently reset" status/priority — no test proves it.
`UpdateTest` always sends both or neither alongside other assertions.
**Add:** PATCH with only `body`, assert `status`/`priority`/`tags` unchanged. This is
the exact contract the autosave composable depends on; if someone "simplifies"
`fillableAttributes()` to always write defaults, nothing fails today.

### [Medium] No test for search terms containing wildcard characters
Relates to the SQLite `LIKE`-escaping bug (see `01-backend-and-database.md`).
**Add:** create a prompt with body `discount is 100% off` and one with `100 percent`,
search `q=100%`, assert only the literal match returns. This test written today
**fails**, which is the point.

### [Low] The create-then-title flow the frontend actually performs is untested
`usePromptAutosave` follows every titled create with `PATCH {title}` and nothing
else — which the backend rejects (422, `body` required). A feature test mirroring the
frontend's real request sequence (POST body → PATCH title-only) would have caught the
lost-title bug in `02-frontend.md`. Add it alongside whichever fix you choose.

### [Low] Account deletion doesn't assert the data goes with it
`ProfileUpdateTest` ("user can delete their account") asserts the user row is gone,
not the prompts/projects/tags. The cascade lives in FK constraints, which is exactly
the kind of thing a driver/pragma change silently breaks. **Add:** seed one of each,
delete the account, assert all four tables are empty.

### [Low] `pq:create-user` gaps
`CreateUserCommandTest` covers options-mode happy path, duplicate and invalid email.
Untested: the interactive prompt path (Laravel Prompts is fakeable —
`Prompt::fake()`), and mixed-case email rejection (which per the backend audit should
become normalisation — test the new behaviour when you change it).

## Structure & quality

### [Low] Stale test scaffolding
- `tests/Feature/Projects/ProjectManagementTest.php:10-15` — the `beforeEach`
  disables Vite and `ensure_pages_exist` with a comment saying the Index component
  "is created in a later task". It exists, and `IndexTest` asserts against it with no
  such bypass — delete the block and the stale comment.
- `tests/Unit/ExampleTest.php` ("true is true"), the `toBeOne` expectation and the
  empty `something()` helper in `Pest.php` — starter cruft, delete.
- `phpunit.xml:32-34` sets env for Pulse/Telescope/Nightwatch, none of which are
  installed — harmless, but delete for accuracy.

### [Low] Extend `ShellConsistencyTest` where it has proven its worth
The source-level-pin technique is the project's substitute for a JS harness; two
invariants found broken in this audit are exactly its kind of target:
- the **no-raw-hex** assertion currently covers 5 shell files — extend it to
  `components/prompts/**` (and `composables/useProjectScopeNav.ts`) once those are
  tokenized, or the drift documented in `02-frontend.md` will recur;
- the `useCopyPrompt` fallback requires an element with `data-prompt-body` — nothing
  pins that the attribute exists in `PromptDetailPane.vue`, which is precisely how it
  silently disappeared.

## What's done well
- **Authorization is tested on every single mutating endpoint** — update, delete,
  status, priority, reorder, projects — always as the stranger-gets-404 variant
  matching the `denyAsNotFound` policy, plus guest redirects. This is the strongest
  part of the suite.
- `ReorderTest` is exemplary: cross-bucket ids, partial lists, foreign ids,
  duplicates, empty list — each asserting positions are *unchanged* after rejection,
  not just that an error exists.
- Position semantics (top-of-bucket insert, other-bucket isolation, move-between-
  buckets, delete-project renumbering) are all pinned with exact position values.
- `PromptAutosaveGuardTest` documents a real regression with its repro and pins the
  fix — brittle by design and honest about why.
- Tests assert effects (DB state, exact props) rather than bare status codes;
  factories with states are used throughout; the suite is fast (2.8s) because
  `phpunit.xml` is tuned (in-memory SQLite, bcrypt rounds 4, array drivers).
- `skipUnlessFortifyHas()` keeps feature-gated auth tests honest instead of deleting
  or false-passing them.
