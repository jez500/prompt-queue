# Backend & database audit

Scope: `app/`, `routes/`, `config/`, `database/` — every PHP file read in full.
Severity: **High** = fix soon, **Medium** = real defect or measurable cost, **Low** = polish / consistency.

## Bugs & correctness

### [Medium] Search escaping is broken on SQLite (the default database)
`app/Models/Prompt.php:126-140` (`search` scope)

The scope escapes `%` and `_` with backslashes but never adds an `ESCAPE` clause.
SQLite's `LIKE` has **no default escape character**, so a search for `100%` becomes
`LIKE '%100\%%'`, which looks for a literal backslash — the user gets zero (or wrong)
results for any term containing `%` or `_`. The backslash itself is also not escaped,
so a term containing `\` corrupts the pattern on MySQL too.

**Recommendation:** append an explicit `ESCAPE` clause and escape the escape
character itself. Note that Laravel's `whereLike()` does **not** help here — with
`caseSensitive: false` it compiles to a plain `like` with the raw binding and adds
no escaping. Use a non-backslash escape character: SQLite string literals take no
backslash escapes while MySQL's do, so `ESCAPE '\'` cannot be written portably.
Add a test that searches for a term containing `%`.

> **Fixed** — `Prompt::search` now escapes `!`, `%` and `_` and appends
> `ESCAPE '!'`. Covered by three tests in `PromptModelTest`. Searching `100%`
> returned *zero* results before the fix.

### [Medium] Profile email updates can lock the user out of login
`app/Concerns/ProfileValidationRules.php:40-52`, `config/fortify.php` (`lowercase_usernames => true`)

Fortify lowercases the submitted email at login, but `ProfileUpdateRequest` stores
whatever case the user typed. Save `Jez@Example.com` in settings and the next login
attempt queries `where email = 'jez@example.com'` — on SQLite/Postgres (case-sensitive
`=`) that user can no longer log in. Registration is closed, so settings is the main
path an email ever changes.

**Recommendation:** normalise in the request (`prepareForValidation` → `strtolower`)
and add the `lowercase` rule, mirroring `pq:create-user`. Relatedly,
`app/Console/Commands/CreateUserCommand.php:55` *rejects* mixed-case emails instead
of normalising them — lowercase there too rather than failing validation.

### [Low] Email-verification ceremony is dead code
`app/Models/User.php` (commented `MustVerifyEmail`), `routes/web.php:19`, `routes/settings.php:15`,
`app/Http/Controllers/Settings/ProfileController.php:22,35-37`

`User` does not implement `MustVerifyEmail` and Fortify's `emailVerification` feature
is off, so the `verified` middleware is a no-op, `mustVerifyEmail` is always `false`,
and nulling `email_verified_at` on email change has no observable effect. Harmless,
but it reads as if verification exists and will mislead future changes.

**Recommendation:** delete the ceremony (drop `verified` from route groups, the
`mustVerifyEmail` prop, and the `email_verified_at` nulling) — or implement the
feature for real. Half-present is the worst state.

### [Low] `SecurityController::edit` type-hints a vestigial 2FA request
`app/Http/Controllers/Settings/SecurityController.php:21`, `app/Http/Requests/Settings/TwoFactorAuthenticationRequest.php`

`TwoFactorAuthenticationRequest` (with Fortify's `InteractsWithTwoFactorState`) is
starter-kit residue: `Features::twoFactorAuthentication()` is not enabled and
`settings/Security.vue` renders no 2FA UI. Replace the type-hint with `Request` and
delete the request class, or enable the feature deliberately.

### [Low] `body` max length is measured in characters, MySQL `TEXT` in bytes
`app/Http/Requests/PromptStoreRequest.php:24`, `PromptUpdateRequest.php:26`

`max:65535` counts characters; a body of multibyte characters can exceed the 64KB
byte budget of MySQL `TEXT` and error in strict mode. Irrelevant on SQLite. If MySQL
support matters, use `$table->mediumText()` or lower the validation cap.

## Performance

### [Medium] Every Inertia response re-queries projects (+counts) and tags
`app/Http/Middleware/HandleInertiaRequests.php:47-62`

Shared props run on *every* Inertia render: a projects query with a correlated
`open_prompts_count` subquery plus a tags query. Combined with autosave PATCHes that
redirect `back()` to the full index (which re-fetches every prompt with `project` and
`tags` eager-loaded), a single keystroke-pause costs 5+ queries and a full-payload
response.

**Recommendation:** the frontend autosave should request partial reloads
(`only: ['prompts']` — or even `only: []` when the client already knows the result).
Consider `Inertia::once()`/deferred props for `projects`/`tags` so they refresh only
when mutated. Measure first; at single-user scale this is latency polish, not load.

### [Low] Reorder writes one UPDATE per prompt
`app/Http/Controllers/PromptOrderController.php:18-28`

A drag in a 200-prompt bucket issues 200 UPDATEs inside the transaction. Fine today;
if buckets grow, collapse to a single `UPDATE ... CASE id WHEN ... THEN ...` or
`upsert()`. Same pattern in `ProjectController::destroy` (per-prompt `update()` loop).

## Maintainability & consistency

### [Low] Authorization lives in three different places
- Controller `Gate::authorize` — `PromptController::update/destroy`, `ProjectController`
- FormRequest `authorize()` — `PromptStatusRequest`, `PromptPriorityRequest`
- Implicit via validation — `PromptReorderRequest::after()` (ids must exactly match the bucket)

All are *correct* (verified each endpoint), but a reader must check three spots to
answer "who can call this?". Pick one convention — controller-level `Gate::authorize`
is the most visible — and move the status/priority checks there.

### [Low] The raw `User` model is shared as an Inertia prop
`app/Http/Middleware/HandleInertiaRequests.php:44-46`

`#[Hidden]` protects the secret columns today, but any future column added to `users`
ships to the client silently. Projects already go through `ProjectResource`; give the
user the same treatment (`id`, `name`, `email` is all the frontend uses).

### [Low] Small dead code / drift
- `HandleInertiaRequests::version()` overrides only to call `parent::version()` — delete.
- `PromptController::destroy(Request $request, ...)` — `$request` is unused.
- `routes/web.php:20` `Route::redirect('dashboard', 'prompts')` and
  `bootstrap/app.php` `redirectUsersTo('/prompts')` hardcode paths where named routes
  (`route('prompts.index')`) are the project convention.

### [Low] Implicit "advance" contract on the status endpoint
`app/Http/Controllers/PromptStatusController.php:17-31`

A PATCH with **no** `status` field means "advance Todo → Implementing after copy".
That contract is invisible in the route and easy to trip over from a new caller (an
accidental empty PATCH silently advances the prompt). An explicit `advance: true`
parameter (or a dedicated `prompts/{prompt}/advance` route) would make the two
behaviours self-describing.

## Database

### [Low] Tag and project uniqueness is case-sensitive
`database/migrations/..._create_tags_table.php:20`

`unique(['user_id', 'name'])` is byte-wise on SQLite, so `Rust` and `rust` are two
tags, and the tag filter (`withTagNames`, exact match) treats them as unrelated.
**Recommendation:** normalise tag names on write (e.g. trim + case-fold in
`SyncPromptTags`) or accept and document it.

### Done well
- FKs with correct cascade semantics: user delete cascades everything;
  project delete would `nullOnDelete` prompts (and the controller re-buckets them
  transactionally anyway).
- `index(['user_id', 'project_id', 'position'])` matches the hot query exactly.
- Composite primary key on `prompt_tag` — no id column, no duplicate links.
- `SyncPromptTags` races are backstopped by the unique constraint.
- Factories are exemplary: ownership states (`forUser`, `inProject` inheriting the
  project's owner), enum states, position state.

## What's done well (backend)
- Policies deny as **not found**, so strangers can't probe for prompt/project existence.
- `Rule::exists('projects', 'id')->where('user_id', ...)` closes the cross-tenant
  project-assignment hole most apps miss.
- `PromptReorderRequest::after()` requires the submitted id set to exactly equal the
  bucket — no silent partial reorders, race-safe by construction.
- Every write path is wrapped in a transaction where multiple rows move together.
- `canReorder()` server-computed: drag is only offered in a single unfiltered bucket,
  so the UI can't produce an ambiguous order.
- `DB::prohibitDestructiveCommands` in production; strict prod-only password rules;
  login rate-limited per email+IP; throttled password updates.
- Enum casts + `Rule::enum` end to end; no stringly-typed statuses in app code.
