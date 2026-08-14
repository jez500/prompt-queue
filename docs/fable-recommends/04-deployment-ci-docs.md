# Deployment, CI & documentation audit

Scope: `Dockerfile`, `docker/entrypoint.sh`, `docker-compose.yml`, `.dockerignore`,
`.github/`, `.lando.yml`, `vite.config.ts`, README, env examples, composer/npm
scripts. Settled decisions from `.ai/rules/deployment.md` and `local-dev.md`
(three-stage image, boot-time config cache, key-generation escape hatch, the compose
`APP_KEY` guard, Lando's recipe-less setup) are respected and were verified accurate.

## Docker image & runtime

### [Medium] `QUEUE_CONNECTION: database` with nothing consuming the queue
`docker-compose.yml:41` sets the database queue driver, but the container runs
exactly one process (FrankenPHP) — no worker, no scheduler. Nothing queues today
(the Fortify reset mail is sent synchronously), so this is a landmine rather than a
bug: the first `ShouldQueue` job anyone adds will sit in the `jobs` table forever
with no error. **Recommendation:** set `QUEUE_CONNECTION: sync` in compose to match
reality, and leave a comment explaining that adding real queue/schedule work means
adding a worker process (supervisor or a second service). Same for the scheduler if
`routes/console.php` ever gains an entry.

### [Medium] Fonts load from Google at runtime; the self-hosted font is the wrong one
`resources/css/app.css:1-2`, `vite.config.ts:31-35`

The app's actual fonts (Space Grotesk, IBM Plex Sans/Mono) are `@import`ed from
`fonts.googleapis.com` at runtime, while the build pipeline's Bunny fonts plugin
downloads and self-hosts **Instrument Sans** — a starter-kit leftover no rule or
stylesheet references. Consequences for a self-hosted app: LAN/offline deployments
silently render fallback fonts (everything else works offline), every visitor's IP
goes to Google (GDPR-relevant), the `@import` is render-blocking, and the image
ships an unused font family. **Recommendation:** point the `fonts:` config at the
three real families via Bunny, rely on the existing `@fonts` blade directive, and
delete both Google `@import` lines.

### [Low] No production PHP ini / OPcache tuning
`Dockerfile` installs `opcache` but ships default settings: `validate_timestamps=1`
(stat calls on every request for code that never changes in a container) and the
development `php.ini`. **Recommendation:** in the runtime stage,
`RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"` and add a small
ini: `opcache.validate_timestamps=0`, `opcache.memory_consumption=128`,
`opcache.max_accelerated_files=20000`. Free latency on every request.

### [Low] Base image rides a floating tag
`FRANKENPHP_VERSION=1-php8.3-alpine` re-resolves on every build, so two builds of
the same commit can differ. Reasonable for a hobby image; if reproducibility starts
mattering, pin the digest and let Dependabot bump it — which requires the next
finding anyway.

## CI

### [Medium] Dependabot only watches GitHub Actions
`.github/dependabot.yml` has one ecosystem. Composer, npm and the Docker base image
— the three surfaces where a security patch actually matters for a published,
internet-adjacent image — get no update PRs. **Recommendation:** add `composer`,
`npm` and `docker` ecosystems (grouped, weekly, same cooldown).

### [Medium] The Docker image is never built before publish
`docker.yml` triggers only on push to `main` and tags — a PR that breaks the
Dockerfile (or the Wayfinder asset stage, which has real moving parts) is discovered
*after* merge, when the publish job fails. **Recommendation:** add a build-only job
(`push: false`, single platform, GHA cache) to `tests.yml` or a PR-triggered
workflow. With layer cache it's cheap.

### [Low] `tests.yml` has no dependency caching and no concurrency group
Every run cold-installs Composer and npm (`composer setup` also builds assets).
`setup-node` supports `cache: npm` natively; `shivammathur/setup-php` documents a
composer-cache pattern; `docker.yml` already has the concurrency group `tests.yml`
lacks, so stacked pushes to one PR run redundantly. Also `shivammathur/setup-php` is
pinned to a mutable `# v2` — resolve the comment to the exact release like the other
pins.

## Documentation

### [Medium] README's password guidance contradicts the code
`README.md` ("Create your login"): *"at least 8 characters with mixed case and a
number."* `AppServiceProvider:41-47` in production actually requires **min 12,
mixed case, letters, numbers, symbols, and an HIBP (uncompromised) check**. A user
following the README gets a validation failure with rules they weren't told about —
on their very first command. Fix the README, and mention the HIBP check needs
outbound HTTPS (relevant to airgapped self-hosts; see backend audit for making it
configurable).

### [Medium] The documented SQLite backup can produce a corrupt copy
`README.md` and the compose header both recommend
`docker compose exec app cat /app/database/database.sqlite > backup.sqlite` — a raw
read of a live database file. If a write lands mid-`cat`, the backup is torn; if
WAL mode is ever enabled, the `-wal` file is silently missed. **Recommendation:**
document `VACUUM INTO` (atomic, works while live), e.g. a `pq:backup {path}` artisan
command — it's five lines, makes the README one-liner honest, and is a natural
sibling to `pq:create-user`.

### [Medium] README advertises keyboard shortcuts that don't exist
"**Copy to clipboard** with ⌘C", "⌘K to search" — neither is implemented (see
`02-frontend.md`). Whichever way that's resolved, the README and the UI hint chips
must move together.

## What's done well
- The entrypoint is genuinely well-designed: root-then-`su-exec` drop, bootstrap
  gated to server start with the key-generation escape hatch, `exec` chains
  preserving PID 1 signal handling, config cached at boot so it matches the real
  environment.
- The `APP_KEY` guard failure mode is documented consistently in four places
  (compose header, env example, README, the guard's own error message) — rare care.
- `.dockerignore` is excellent: secrets, editor state, agent configs, planning docs,
  tests all excluded; the image contains what it should.
- Healthchecks in both the image and compose against the framework's `/up` route.
- CI actions pinned by SHA with version comments per the project's own rule;
  publish workflow has a concurrency group; multi-arch with GHA layer cache.
- CI runs the exact same gate as local (`composer ci:check`) — no drift between
  "passes on my machine" and "passes in CI".
- `.lando.yml` documents *why* for every non-obvious choice (recipe-less, Apache
  over nginx, HSTS-forced SSL, the 5273 port and `VITE_DEV_SERVER_ORIGIN` dance) —
  each one verified against `vite.config.ts` and true.
- README install path was verified command-by-command against the image and
  entrypoint (including `php artisan dev` existing) — apart from the three findings
  above, it's accurate and unusually readable.
