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
small trusted group, not opened to the internet. Optional single sign-on
through [Authelia](https://www.authelia.com) logs into accounts you've already
created.

---

## Quick start

```bash
mkdir prompt-queue && cd prompt-queue
curl -O https://raw.githubusercontent.com/jez500/prompt-queue/main/docker-compose.yml
curl -o .env https://raw.githubusercontent.com/jez500/prompt-queue/main/.env.docker.example

# Put the printed key into APP_KEY in .env
docker run --rm jez500/prompt-queue php artisan key:generate --show

docker compose up -d
docker compose exec app php artisan pq:create-user
```

Then open <http://localhost:8080>. Full walkthrough, configuration, backups and
reverse-proxy notes: [docs/self-hosting.md](docs/self-hosting.md).

---

## Docs

- [**Self-hosting**](docs/self-hosting.md) — install, configuration, backup and
  restore, reverse proxies, MySQL.
- [**Authentication**](docs/authentication.md) — creating users, password
  rules, and single sign-on with Authelia.
- [**Development**](docs/development.md) — local setup, Lando, the check
  commands.
- [`DESIGN.md`](DESIGN.md) — the design system: layout, tokens, components.
- [`.ai/rules/`](.ai/rules) — conventions and traps for agents working in the
  codebase. Start at [`index.md`](.ai/rules/index.md).

---

## Stack

Laravel 13 on PHP 8.3, Inertia v3 with Vue 3, Tailwind v4, shadcn-vue on
reka-ui, Laravel Fortify for auth, Socialite for single sign-on, Wayfinder for
typed routes, Pest 4 for tests. The production image is FrankenPHP — one
process, no nginx or php-fpm.
