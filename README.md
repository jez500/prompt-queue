# Prompt Queue

A place to park the prompts you want to hand an agent, so the good ones stop
living in scratch files and browser tabs.

Write a prompt, tag it, file it under a project, and pull it back out with one
click when you're ready to run it. Self-hosted, single container, SQLite by
default.

![Docker image](https://img.shields.io/docker/v/jez500/prompt-queue?label=docker&sort=semver)

---

## What it does

- **Queue prompts** with a title and body, autosaved as you type.
- **Organise** by project (colour-coded) and freeform tags.
- **Track state** — Todo → Implementing → Done, plus High/Normal/Low priority.
- **Find things** with full-text search and status/priority/tag filters.
- **Reorder** by dragging within a project.
- **Copy to clipboard** with `⌘C` and paste straight into your agent.
- **Keyboard-first** — `N` for a new prompt, `⌘K` to search, `⌘C` to copy.
  (`Ctrl` stands in for `⌘` away from a Mac.)

Registration is deliberately closed: this is built to be run for yourself or a
small trusted group, not opened to the internet.

---

## Install with Docker Compose

You need Docker with Compose v2.

**1. Get the compose file**

```bash
mkdir prompt-queue && cd prompt-queue
curl -O https://raw.githubusercontent.com/jez500/prompt-queue/main/docker-compose.yml
curl -o .env https://raw.githubusercontent.com/jez500/prompt-queue/main/.env.docker.example
```

**2. Generate an application key**

```bash
docker run --rm jez500/prompt-queue php artisan key:generate --show
```

Paste the `base64:…` output into `APP_KEY` in `.env`.

> Use plain `docker run` here, not `docker compose run` — compose checks
> `APP_KEY` on every subcommand, so it can't generate the key it's asking for.

**3. Start it**

```bash
docker compose up -d
```

**4. Create your login**

```bash
docker compose exec app php artisan pq:create-user
```

It'll prompt for a name, email and password. In production the password rules
are strict: at least 12 characters, with upper and lower case, a number and a
symbol. It is also checked against the Have I Been Pwned breach list, which
needs outbound HTTPS — on an air-gapped host, relax `Password::defaults()` in
`AppServiceProvider`. Non-interactively:

```bash
docker compose exec app php artisan pq:create-user \
    --name="Jez" --email="you@example.com" --password="…"
```

Then open <http://localhost:8080>.

### Configuration

Everything below is optional and set in `.env`.

| Variable | Default | Notes |
| --- | --- | --- |
| `APP_KEY` | — | **Required.** See step 2. |
| `APP_PORT` | `8080` | Host port to publish on. |
| `APP_URL` | `http://localhost:8080` | Set this to your real URL behind a proxy. |
| `APP_NAME` | `Prompt Queue` | Shown in the browser title. |
| `MAIL_MAILER` | `log` | Only used for password resets. Left as `log`, reset links go to the container log instead of being emailed. |

Data lives in the `prompt-queue-data` volume, mounted at `/app/database`.

```bash
# Back up — VACUUM INTO takes a consistent snapshot while the app is running,
# which copying the live file does not.
docker compose exec app php artisan pq:backup /app/database/backup.sqlite
docker compose cp app:/app/database/backup.sqlite ./backup.sqlite
docker compose exec app rm /app/database/backup.sqlite

# Restore — stop the app first, then put the file back.
docker compose down
docker compose cp ./backup.sqlite app:/app/database/database.sqlite
docker compose up -d

# Update
docker compose pull && docker compose up -d    # migrations run on boot
```

### Behind a reverse proxy

The container serves plain HTTP on `8080`; terminate TLS at your proxy. Set
`APP_URL` to the public HTTPS URL so generated links and redirects are correct.

### Using MySQL instead

SQLite suits this workload and is the default. `docker-compose.yml` ships a
commented MySQL service — uncomment it, the `prompt-queue-db` volume, the
`depends_on` block and the `DB_*` variables, then set `DB_PASSWORD` and
`DB_ROOT_PASSWORD` in `.env`.

---

## Local development

Requires PHP 8.3, Composer and Node 22.

```bash
git clone https://github.com/jez500/prompt-queue.git
cd prompt-queue
composer setup     # install deps, write .env, generate key, migrate, build assets
composer dev       # serve + vite + queue listener + logs
php artisan pq:create-user
```

The app runs at <http://localhost:8000>.

### Or with Lando

If you'd rather not install PHP and Node locally, `.lando.yml` builds a
matching container. Requires [Lando](https://lando.dev) and Docker.

```bash
lando start
lando setup            # env, key, database, assets
lando create-user
lando vite             # dev server with HMR, in a second terminal
```

The app is served at <https://prompt-queue.lndo.site>. Tooling runs inside the
container: `lando artisan`, `lando composer`, `lando npm`, `lando node`,
`lando test`, `lando check`, `lando pint`, `lando phpstan`.

After changing `.lando.yml`, run `lando rebuild -y` — `lando restart` does not
pick up environment or build changes.

> **If the hostname 404s**, Lando could not write its `/etc/hosts` entry (it
> needs root) and the name is falling through to wildcard DNS. Add it by hand:
>
> ```bash
> echo "127.0.0.1 prompt-queue.lndo.site" | sudo tee -a /etc/hosts
> ```
>
> Use the **https** URL. `*.lndo.site` is HSTS-preloaded, so browsers refuse
> plain HTTP even though `curl http://…` works.

### Checks

```bash
composer ci:check      # everything CI runs
php artisan test --compact
npm run types:check    # vue-tsc
npm run lint:check     # eslint
```

Run `vendor/bin/pint --dirty` after touching PHP.

---

## Stack

Laravel 13 on PHP 8.3, Inertia v3 with Vue 3, Tailwind v4, shadcn-vue on
reka-ui, Laravel Fortify for auth, Wayfinder for typed routes, Pest 4 for
tests. The production image is FrankenPHP — one process, no nginx or php-fpm.

## Docs

- [`DESIGN.md`](DESIGN.md) — the design system: layout, tokens, components.
- [`.ai/rules/`](.ai/rules) — conventions and traps for agents working in the
  codebase. Start at [`index.md`](.ai/rules/index.md).
