# Testing

**Globs:** `tests/**`, `app/**`, `database/**`

## Every change is programmatically tested

Write or update a test, then run it. `php artisan test --compact` for the
suite, `--filter=Name` while iterating. Pest 4, feature tests by default.

Full gate before finishing (this is what CI runs via `composer ci:check`):

```
npm run lint:check && npm run format:check && npm run types:check
composer test          # pint --test, phpstan, artisan test
```

## Conventions

- Use factories, and check for existing states before building models by hand.
- Feature tests over unit tests unless the logic is genuinely standalone.
- Do not delete tests without asking.

## There is no client-side test harness

No Vitest, and Pest 4 browser testing would need Playwright installed — a
dependency change. So Vue behaviour is **not** covered by automated tests.

Two consequences:

1. Verify frontend work in a real browser before calling it done, at both the
   narrow (`≤1099px`) and compact (`1100–1259px`) breakpoints, not just wide.
2. Where a frontend invariant matters structurally, pin it with a source-level
   assertion instead. `tests/Feature/ShellConsistencyTest.php` does this — it
   asserts layouts resolve centrally, no page declares its own chrome, the
   retired starter-kit shell stays deleted, both list panes use the shared
   primitives, and shell files contain no raw hex.

That file is a guard rail, not a formality. If you are deliberately changing
one of those invariants, change the test in the same commit and say why.

## Password rules differ by environment

`AppServiceProvider` applies strict `Password::defaults()` (mixed case, numbers)
**in production only**. A password that passes in tests can be rejected by
`pq:create-user` inside the production container. Use a realistic password when
testing that path.
