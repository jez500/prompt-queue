# Local development

Requires PHP 8.3, Composer and Node 22.

```bash
git clone https://github.com/jez500/prompt-queue.git
cd prompt-queue
composer setup     # install deps, write .env, generate key, migrate, build assets
composer dev       # serve + vite + queue listener + logs
php artisan pq:create-user
```

The app runs at <http://localhost:8000>.

## Or with Lando

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

## Checks

```bash
composer ci:check      # everything CI runs
php artisan test --compact
npm run types:check    # vue-tsc
npm run lint:check     # eslint
```

Run `vendor/bin/pint --dirty` after touching PHP.

## Frontend routes are generated

`resources/js/actions`, `resources/js/routes` and `resources/js/wayfinder` are
built by Wayfinder and gitignored. The Vite plugin runs
`php artisan wayfinder:generate` during `npm run build`, so any build
environment needs PHP, `vendor/` and a bootable app — not just Node.

## Conventions

Agent-facing conventions and traps live in [`.ai/rules/`](../.ai/rules), which
is worth reading before changing anything structural. Start at
[`index.md`](../.ai/rules/index.md). The design system — layout, tokens,
components — is in [`DESIGN.md`](../DESIGN.md).
