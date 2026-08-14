# Deployment

**Globs:** `Dockerfile`, `docker/**`, `docker-compose.yml`, `.github/workflows/**`

## Image layout

Three stages, published as `jez500/prompt-queue`:

1. **`vendor`** (`composer:2`) — production PHP dependencies, optimised autoloader.
2. **`assets`** (FrankenPHP + Node) — needs **both** runtimes, because the
   Wayfinder Vite plugin runs `php artisan wayfinder:generate` before Vite
   bundles. It writes a throwaway `.env`, generates a key, builds, then deletes
   the `.env`. That key never reaches runtime.
3. **`runtime`** (FrankenPHP alpine) — app + vendor + `public/build`.

FrankenPHP serves on `:8080` via `SERVER_NAME`. No nginx, no php-fpm, no
supervisor — one process.

## Entrypoint

`docker/entrypoint.sh` starts as root to fix ownership on a freshly-mounted
volume, then drops to the unprivileged `app` user with `su-exec`. The server
runs as `app` (uid 1000).

Bootstrapping — the `APP_KEY` check, config/route/view caching and
`migrate --force` — runs **only when starting the web server**. One-off
commands pass straight through. This is deliberate:

> `php artisan key:generate --show` must work in a container that has no
> `APP_KEY` yet. Gating it behind the key check makes the key ungeneratable.

Config is cached at boot, not baked at build, so it reflects the environment
the container was actually started with.

### Traps

- **Caddy needs writable `/data/caddy` and `/config/caddy`.** It provisions a
  local CA on boot; as non-root this fails with `permission denied` and the
  server exits. The Dockerfile chowns both.
- **`docker compose run` cannot generate the key.** The `${APP_KEY:?...}` guard
  in `docker-compose.yml` is evaluated for *every* compose subcommand, so
  compose can't produce the key it's demanding. Docs use plain `docker run` for
  that one step. If you relax the guard, update the docs to match.
- **`docker exec` bypasses the entrypoint**, so it runs as root. Fine for
  one-off artisan commands; don't rely on it to drop privileges.

## Publishing

`.github/workflows/docker.yml`, multi-arch `linux/amd64,linux/arm64`:

- push to `main` → `:latest`, `:main-<sha>`
- tag `v1.2.3` → `:1.2.3`, `:1.2`, `:latest`

Requires repo secrets **`DOCKERHUB_USERNAME`** and **`DOCKERHUB_TOKEN`**.

Pin actions by commit SHA with a trailing version comment, matching
`tests.yml`. Never invent a SHA — resolve it (`gh api repos/OWNER/REPO/commits/TAG --jq .sha`).

### Bumping the tag means incrementing the last number

`v1.0.1` → `v1.0.2` → `v1.0.3`. **Always.** Do not read the size of a change
and decide it deserves `v1.1.0` — a new feature is still a last-number bump.
Jez will say so explicitly when a release should move the first or second
number; until then that is not a judgement call to make.

Getting it wrong is expensive to undo. The tag publishes to Docker Hub, and
deleting a git tag does not unpublish an image: a mistaken `v1.1.0` leaves
`:1.1.0` and `:1.1` sitting in the registry forever, and `:latest` points at
that build until something later replaces it.

## Registration is closed

`config/fortify.php` enables only `resetPasswords()`. The first user is created
with `php artisan pq:create-user`. If you ever enable `Features::registration()`,
say so loudly in the README and `docs/authentication.md` — it changes a
self-hosted instance from invite-only to open signup.

Authelia single sign-on is optional and does not change this: it links a
provider login to an account that already exists and refuses when none does.
See [actions.md](actions.md) before touching that path.
